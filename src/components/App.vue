<template>
    <div id="app">
        <div class="safe-mode" v-if="safeMode && view === 'ready'">
            <strong class="safe-mode__headline">{{ $t('ui.app.safeModeHeadline') }}</strong>&nbsp;
            <span class="safe-mode__description">{{ $t('ui.app.safeModeDescription') }}</span>&nbsp;
            <a class="safe-mode__link" href="javascript:window.location.reload()">{{ $t('ui.app.safeModeExit') }}</a>
        </div>

        <div class="https-warning" v-else-if="isInsecure">
            <strong class="https-warning__headline">{{ $t('ui.app.httpsHeadline') }}</strong>&nbsp;
            <span class="https-warning__description">{{ $t('ui.app.httpsDescription') }}</span>&nbsp;
            <a :href="$t('ui.app.httpsHref')" target="_blank" class="https-warning__link">{{ $t('ui.app.httpsLink') }}</a>
        </div>

        <error v-if="error"/>

        <transition name="animate-fade" mode="out-in" style="height:100%">

            <div v-if="isInitializing || isReady" class="view-init">
                <div class="view-init__cell animate-initializing">
                    <img src="../assets/images/logo.svg" width="100" height="100" alt="Contao Logo">
                    <p class="view-init__message">{{ $t('ui.app.loading') }}</p>
                </div>
            </div>

            <task :class="hasModal ? 'animate-blur-in' : 'animate-blur-out'" v-else-if="username && taskStatus"/>
            <component :is="currentView" :class="hasModal ? 'animate-blur-in' : 'animate-blur-out'" v-else-if="currentView"/>

            <div v-else>
                <router-view :class="hasModal ? 'animate-blur-in' : 'animate-blur-out'"/>
            </div>

        </transition>

        <component :is="currentModal" v-if="hasModal"/>
    </div>
</template>

<script>
    import { mapState, mapGetters } from 'vuex';
    import views from '../router/views';

    import PackageDetails from './fragments/PackageDetails';
    import Loader from 'contao-package-list/src/components/fragments/Loader';

    import Error from './views/Error';
    import Account from './views/Account';
    import Login from './views/Login';
    import Task from './views/Task';
    import Boot from './views/Boot';
    import Setup from './views/Setup';
    import Recovery from './views/Recovery';
    import Migration from './views/Migration';

    export default {
        components: { Loader, Error, Task },

        data: () => ({
            views: {
                [views.ACCOUNT]: Account,
                [views.LOGIN]: Login,
                [views.BOOT]: Boot,
                [views.SETUP]: Setup,
                [views.RECOVERY]: Recovery,
                [views.MIGRATION]: Migration,
            },
            loaded: false,
        }),

        computed: {
            ...mapState(['safeMode']),
            ...mapState(['view', 'error']),
            ...mapState('auth', ['username']),
            ...mapState('tasks', { taskStatus: 'status' }),
            ...mapGetters('modals', ['hasModal', 'currentModal']),

            isInitializing: vm => vm.view === views.INIT,
            isReady: vm => !vm.isInitializing && !vm.currentView && !vm.loaded,
            isInsecure: () => location.protocol !== 'https:' && location.hostname !== 'localhost',

            currentView: vm => vm.views[vm.view] || null,
        },

        watch: {
            async isReady(ready) {
                if (ready) {
                    try {
                        await this.$store.dispatch('packages/uploads/load');
                        await this.$store.dispatch('packages/load');
                        await this.$store.dispatch('algolia/discover');
                    } catch (err) {
                        // do nothing
                    }

                    this.loaded = true;
                    this.$store.dispatch('packages/details/init', { vue: this, component: PackageDetails });
                }
            },

            username(username) {
                if (username === null) {
                    this.$store.commit('tasks/setCurrent', null);
                    this.$store.commit('tasks/setInitialized', false);
                }
            },
        },

        async mounted() {
            const accountStatus = await this.$store.dispatch('auth/status');

            const chunks = location.pathname.split('/').filter(v => v !== '');
            chunks.unshift('');

            while (chunks.pop() !== undefined && chunks.length) {
                try {
                    const config = (await this.$http.get(`${chunks.join('/')}/contao-manager/users.json`, { responseType: 'json' })).body;

                    if (!config.hasOwnProperty('users') && !config.hasOwnProperty('version')) {
                        continue;
                    }

                    this.$store.commit('setError', {
                        title: this.$t('ui.app.configSecurity1'),
                        type: 'about:blank',
                        status: '500',
                        detail: this.$t('ui.app.configSecurity2'),
                    });

                    return;
                } catch (err) {
                    // user.json could not be loaded, seems like a valid config
                }
            }

            if (accountStatus === 200) {
                this.$store.commit('setView', views.BOOT);
            } else if (accountStatus === 204) {
                this.$store.commit('setView', views.ACCOUNT);
            } else if (accountStatus === 401 || accountStatus === 403) {
                this.$store.commit('setView', views.LOGIN);
            } else {
                this.$store.commit('apiError', { status: accountStatus });
            }
        },

        created() {
            document.title = `Contao Manager | ${location.hostname}`;
        },
    };
</script>

<style rel="stylesheet/scss" lang="scss">
    $icons: (
        'add',
        'check',
        'cloud',
        'cloud-off',
        'console',
        'database',
        'edit',
        'gear',
        'hide',
        'details',
        'link',
        'lock',
        'maintenance',
        'more',
        'power',
        'run',
        'save',
        'search',
        'show',
        'trash',
        'unlock',
        'update',
        'upload',
    );

    @import "~contao-package-list/src/assets/styles/defaults";
    @import "~contao-package-list/src/assets/styles/layout";
    @import "~contao-package-list/src/assets/styles/animations";

    .https-warning {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 27px;
        padding: 4px 8px;
        background: $orange-button;
        color: #fff;
        text-align: center;
        z-index: 100;

        &__description {
            display: none;

            @include screen(600) {
                display: inline;
            }
        }

        &__link {
            color: #fff;
            text-decoration: underline;
        }

        + div {
            padding-top: 25px;
        }
    }

    .safe-mode {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 27px;
        padding: 4px 8px;
        background: $red-button;
        color: #fff;
        text-align: center;
        z-index: 100;

        &__description {
            display: none;

            @include screen(600) {
                display: inline;
            }
        }

        &__link {
            color: #fff;
            text-decoration: underline;
        }

        + div {
            padding-top: 25px;
        }
    }

    .view-init {
        display: table;
        width: 100%;
        height: 100%;

        &__cell {
            display: table-cell;
            font-size: 1.5em;
            text-align: center;
            vertical-align: middle;
        }
    }
</style>
