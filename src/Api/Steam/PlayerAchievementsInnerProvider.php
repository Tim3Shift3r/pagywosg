<?php

namespace App\Api\Steam;

use App\Api\Steam\Schema\Achievement;
use App\Framework\Exceptions\UnexpectedResponseException;
use App\Framework\Steam\Api\JsonResponseApiProvider;
use GuzzleHttp\Exception\GuzzleException;

class PlayerAchievementsInnerProvider extends JsonResponseApiProvider
{
    protected function getUrl()
    {
        return 'https://api.steampowered.com/ISteamUserStats/GetPlayerAchievements/v1/';
    }

    /**
     * @param $steamid
     * @param $appid
     * @return Achievement[]
     * @throws UnexpectedResponseException
     * @throws GuzzleException
     */
    public function fetch($steamid, $appid)
    {
        $achievements = [];
        foreach ($this->getEssence(compact('steamid', 'appid')) as $achievement) {
            $achievements[] = (new Achievement)
                ->setPlayer($steamid)
                ->setAppid($appid)
                ->setAchieved($achievement['achieved'])
                ->setApiname($achievement['apiname'])
                ->setUnlocktime($achievement['unlocktime']);
        }

        return $achievements;
    }

    protected function getEssenceValue($response)
    {
        if (isset($response['playerstats']['error']) && $response['playerstats']['error'] === 'Requested app has no stats')
            return [];

        return $response['playerstats']['achievements'];
    }
}
