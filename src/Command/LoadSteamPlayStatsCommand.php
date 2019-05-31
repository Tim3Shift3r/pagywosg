<?php

namespace App\Command;

use App\Api\Steam\OwnedGamesInnerProvider;
use App\Api\Steam\PlayerAchievementsInnerProvider;
use App\Api\Steam\Schema\Achievement;
use App\Entity\Change;
use App\Entity\EventEntry;
use App\Entity\Game;
use App\Entity\User;
use App\Framework\Command\BaseCommand;
use App\Framework\Exceptions\UnexpectedResponseException;
use DateTime;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LoadSteamPlayStatsCommand extends BaseCommand
{
    /** @var OwnedGamesInnerProvider */
    protected $ownedGamesProvider;

    /** @var PlayerAchievementsInnerProvider */
    protected $playerAchievementsProvider;
    
    public function __construct(
        OwnedGamesInnerProvider $ownedGamesProvider,
        PlayerAchievementsInnerProvider $playerAchievementsProvider
    ) {
        parent::__construct();
        
        $this->ownedGamesProvider = $ownedGamesProvider;
        $this->playerAchievementsProvider = $playerAchievementsProvider;
    }

    protected function configure()
    {
        $this->setName('steam:play-stats:load')
            ->getDefinition()
            ->setOptions([
                new InputOption('all', null, InputOption::VALUE_OPTIONAL, '', false)
            ])
        ;
    }

    protected function proceed(InputInterface $input, OutputInterface $output)
    {
        $qb = $this->em->createQueryBuilder()
            ->select('entry')
            ->from(EventEntry::class, 'entry')
            ->leftJoin('entry.event', 'event')
            ->where('event.endedAt > :now')
            ->setParameter('now', new DateTime);

        if (!$input->getOption('all')) {
            $qb->andWhere($qb->expr()->orX(
                'entry.refreshedAt is null',
                'entry.refreshedAt <= :yesterday'
            ))
            ->setParameter('yesterday', new DateTime('-1 day'));
        }

        /** @var EventEntry[] $entries */
        $entries = $qb->getQuery()->getResult();

        $this->ss->note('Entries to update: '.count($entries));

        if (!$entries) {
            return;
        }

        // mark as refreshed instantly
        $now = new DateTime;
        foreach ($entries as $entry) {
            $entry->setRefreshedAt($now);
        }
        $this->em->flush();

        $entryKeys = [];
        $playerIds = [];
        $gameIds = [];

        foreach ($entries as $entry) {
            $entryKeys[] = $this->getEntryKey($entry->getPlayer()->getSteamId(), $entry->getGame()->getId());
            $playerId[] = $entry->getPlayer()->getId();
            $gameIds[] = $entry->getGame()->getId();
        }

        $entries = array_combine($entryKeys, $entries);

        // prevent lazy-load
        $this->em->getRepository(User::class)->findBy(['id' => $playerIds]);
        $this->em->getRepository(Game::class)->findBy(['id' => $gameIds]);

        $playerGames = array_fill_keys($playerIds, []);
        foreach ($entries as $entry) {
            $playerId = $entry->getPlayer()->getSteamId();

            $playerGames[$playerId][] = $entry->getGame()->getId();
        }
        $playerGames = array_filter($playerGames);

        try {
            $this->updatePlaytime($playerGames, $entries);
        } catch (GuzzleException|UnexpectedResponseException $e) {
            $this->ss->error($e->getMessage());
        }

        try {
            $this->updateAchievementsCount($entries);
        } catch (GuzzleException|UnexpectedResponseException $e) {
            $this->ss->error($e->getMessage());
        }

        $this->ss->note('Flushing all to database');
        $this->em->flush();
    }

    protected function getEntryKey($playerSteamId, $gameId)
    {
        return "{$playerSteamId}:{$gameId}";
    }

    /**
     * @param array $playerGames
     * @param EventEntry[] $entries
     * @throws GuzzleException
     * @throws UnexpectedResponseException
     */
    protected function updatePlaytime(array $playerGames, array $entries): void
    {
        if (!$playerGames) {
            return;
        }

        $this->ss->note('Updating playtime');
        $this->ss->progressStart(count($playerGames));
        foreach ($playerGames as $playerSteamId => $playerGameIds) {
            try {
                $ownedGames = $this->ownedGamesProvider->fetch($playerSteamId, $playerGameIds);
            } catch (Exception $e) {
                $this->ss->error($e->getMessage());
                continue;
            }

            foreach ($ownedGames as $ownedGame) {
                $entryKey = $this->getEntryKey($playerSteamId, $ownedGame->getAppId());

                if (!isset($entries[$entryKey])) {
                    continue;
                }

                $this->changelog->add(
                    (new Change)
                        ->setObject($entries[$entryKey])
                        ->set('playTime', $entries[$entryKey]->getPlayTime(), $ownedGame->getPlaytimeForever())
                );

                $entries[$entryKey]
                    ->setPlayTime($ownedGame->getPlaytimeForever())
                ;
            }
            $this->ss->progressAdvance();
        }
        $this->ss->progressFinish();
    }

    /**
     * @param EventEntry[] $entries
     * @throws GuzzleException
     * @throws UnexpectedResponseException
     */
    protected function updateAchievementsCount(array $entries)
    {
        if (!$entries) {
            return;
        }

        $this->ss->note('Updating achievements count');
        $this->ss->progressStart(count($entries));
        foreach ($entries as $entry) {
            $achievements = $this->playerAchievementsProvider->fetch(
                $entry->getPlayer()->getSteamId(),
                $entry->getGame()->getId()
            );

            $game = $entry->getGame();
            $gameAchievementsCnt = count($achievements);

            $this->changelog->add(
                (new Change)
                    ->setObject($game)
                    ->set('achievementsCnt', $game->getAchievementsCnt(), $gameAchievementsCnt)
            );

            // Updating total achievement count of the game on the go
            $game->setAchievementsCnt(count($achievements));

            $achievementsCnt = $achievements ? array_reduce(
                $achievements,
                function ($achievementsCnt, Achievement $achievement) {
                    return $achievementsCnt + ($achievement->isAchieved() ? 1 : 0);
                }
            ) : 0;

            $this->changelog->add(
                (new Change)
                    ->setObject($entry)
                    ->set('achievementsCnt', $entry->getAchievementsCnt(), $achievementsCnt)
            );

            $entry->setAchievementsCnt($achievementsCnt);
            $this->ss->progressAdvance();
        }
        $this->ss->progressFinish();
    }
}