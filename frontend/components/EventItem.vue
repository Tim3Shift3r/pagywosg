<template>
    <div
        class="event-item"
        @click="goToEventDetail(event.id, $event)"
    >
        <div class="event-item__top-line">
            <router-link
                :to="{name: 'event_detail_name', params: {eventId: event.id, eventName: urlEventName}}"
                class="event-item__title"
            >{{event.name}}</router-link>
            <div class="event-item__params">
                <div class="event-item__dates">
                    <i class="icon-fa fas fa-calendar-alt"></i>
                    {{event.startedAt}} &mdash; {{event.endedAt}}
                </div>
                <div class="event-item__user-count" title="Event participants count">
                    <i class="icon-fa icon-fa--blue fas fa-users"></i>
                    {{+event.participantCount}}
                </div>
                <div class="event-item__user-count" title="Submitted games count">
                    <i class="icon-fa icon-fa--blue fas fa-gamepad"></i>
                    {{+event.entriesCount}}
                </div>
                <div class="event-item__host" title="The name of the creator of this event">
                    <i class="icon-fa icon-fa--golden fas fa-star"></i>
                    <router-link
                        :to="{name: 'user_profile_name', params: {userId: host.id, userName: urlHostName}}"
                    >{{host.sgProfileName ? host.sgProfileName : host.profileName}}</router-link>
                </div>
            </div>
        </div>

        <div class="event-item__descr text" v-html="compileDescr(event.description)"></div>
    </div>
</template>

<script>
    import marked from 'marked';

    export default {
        name: "EventItem",
        props: {
            event: {
                type: Object,
                default: () => {
                    return {
                        id: 0,
                        name: '',
                        host: 0,
                        startedAt: '',
                        endedAt: '',
                        participantCount: 0,
                        entriesCount: 0,
                        description: '',
                    }
                }
            },
            host: {
                type: Object,
                default: () => {
                    return {
                        id: 0,
                        profileName: '',
                        avatar: '',
                        steamId: '',
                        sgProfileName: ''
                    }
                }
            }
        },
        data: function () {
            return {};
        },
        computed: {
            urlEventName: function () {
                return this.$urlify(this.event.name);
            },

            urlHostName: function () {
                return this.$urlify(this.host.sgProfileName ? this.host.sgProfileName : this.host.profileName)
            }
        },
        methods: {
            goToEventDetail(eventId, $event){

                if ($event.target.tagName === 'A')
                    return;

                this.$router.push({name: 'event_detail', params: {eventId}});
            },

            compileDescr (descr) {
                return marked(descr, {sanitize: true, breaks: true});
            }
        }
    }
</script>

<style lang="less">
    @import "../assets/mixins";

    .event-item{
        border: 1px solid @color-blue;
        border-radius: 4px;
        margin-bottom: 26px;
        box-sizing: border-box;
        padding: 12px 16px;
        box-shadow: 0 0 1px rgba(0, 0, 0, 0);
        transition: box-shadow 0.3s;
        cursor: pointer;

        &:hover{
            box-shadow: 0 0 12px @color-light-blue;
        }

        &__top-line{
            display: flex;
            margin-bottom: 8px;
        }
        
        &__params{
            padding-left: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            align-items: center;
        }

        &__title{
            display: block;
            font-size: 22px;
            color: @color-dark-blue;
            flex-grow: 1;
            text-decoration: none;
            
            &:hover{
                text-decoration: underline;
            }
        }

        &__dates{
            font-size: 12px;
            color: @color-gray;
            white-space: nowrap;
            width: 100%;
            text-align: right;
            margin-bottom: 5px;
        }

        &__user-count{
            font-size: 20px;
            margin-right: 16px;
            color: @color-dark-blue;
        }

        &__descr{
            max-height: 160px;
            overflow: hidden;
            position: relative;
            margin: 0 -16px -12px;
            padding: 0 16px 12px;
            min-height: 50px;
        }
    }

</style>