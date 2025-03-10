<template>
    <boxed-layout :wide="true" slotClass="database-migration">
        <header class="database-migration__header">
            <img src="../../assets/images/database.svg" width="80" height="80" alt="" class="database-migration__icon">
            <h1 class="database-migration__headline">{{ $t('ui.migrate.headline') }}</h1>
            <p class="database-migration__text" v-if="type === 'migrations-only'">{{ $t('ui.migrate.migrationsOnly') }}</p>
            <p class="database-migration__text" v-if="type === 'schema-only'">{{ $t('ui.migrate.schemaOnly') }}</p>

            <template v-if="checking">
                <p class="database-migration__description">{{ $t('ui.migrate.loading') }}</p>
                <div class="database-migration__loading">
                    <loader/>
                </div>
            </template>

            <template class="database-migration__summary" v-else-if="isEmpty">
                <p class="database-migration__description" v-if="type === 'migrations-only'">{{ $t('ui.migrate.emptyMigrations') }}</p>
                <p class="database-migration__description" v-else-if="type === 'schema-only'">{{ $t('ui.migrate.emptySchema') }}</p>
                <p class="database-migration__description" v-else>{{ $t('ui.migrate.empty') }}</p>
                <div class="database-migration__actions">
                    <button class="widget-button widget-button--primary" :disabled="closing" @click="checkAll()" v-if="type === 'migrations-only' || type === 'schema-only'">{{ $t('ui.migrate.retryAll') }}</button>
                    <loading-button :loading="closing" @click="close">{{ $t('ui.migrate.close') }}</loading-button>
                </div>
            </template>

            <template v-else-if="!executing && (isComplete || hasError || hasProblem)">
                <p class="database-migration__description" v-for="(line,i) in description.split('\n')" :key="i">{{ line }}</p>
                <div class="database-migration__actions">
                    <template v-if="type === 'problem'">
                        <loading-button :loading="closing" @click="close">{{ $t('ui.migrate.cancel') }}</loading-button>
                        <button class="widget-button" :disabled="closing" @click="setup">{{ $t('ui.migrate.setup') }}</button>
                        <button class="widget-button widget-button--primary" :disabled="closing" @click="checkAll()">{{ $t('ui.migrate.retry') }}</button>
                    </template>
                    <template v-else-if="type === 'warning'">
                        <loading-button :loading="closing" @click="close">{{ $t('ui.migrate.cancel') }}</loading-button>
                        <button class="widget-button widget-button" :disabled="closing" @click="checkAll()">{{ $t('ui.migrate.retry') }}</button>
                        <button class="widget-button widget-button--primary" :disabled="closing" @click="checkAll(true)">{{ $t('ui.migrate.skip') }}</button>
                    </template>
                    <template v-else-if="hasChanges">
                        <loading-button :loading="closing" @click="close">{{ $t('ui.migrate.cancel') }}</loading-button>
                        <button class="widget-button widget-button--primary" :disabled="closing" @click="check()">{{ $t('ui.migrate.continue') }}</button>
                    </template>
                    <template v-else>
                        <loading-button :loading="closing" @click="close">{{ $t('ui.migrate.confirm') }}</loading-button>
                    </template>
                </div>
            </template>

            <template v-else>
                <p class="database-migration__description">{{ $t('ui.migrate.pending') }}</p>
                <div class="database-migration__actions">
                    <loading-button class="database-migration__action" :loading="closing" :disabled="executing" @click="close">{{ $t('ui.migrate.cancel') }}</loading-button>
                    <loading-button class="database-migration__action" color="primary" :loading="executing" :disabled="closing" @click="execute">{{ $t('ui.migrate.execute') }}</loading-button>
                </div>
                <div class="database-migration__actions" v-if="hasDeletes">
                    <checkbox name="autoclose" :label="$t('ui.migrate.withDeletes')" :disabled="executing" v-model="withDeletes"/>
                </div>
            </template>

        </header>

        <console
            class="database-migration__main"
            :title="consoleTitle"
            :operations="operations"
            :console-output="console"
            :show-console.sync="showConsole"
            :force-console="hasProblem"
            v-if="!checking && operations && operations.length"
        />
    </boxed-layout>
</template>

<script>
    import { mapState, mapGetters } from 'vuex';
    import views from '../../router/views';

    import BoxedLayout from '../layouts/Boxed';
    import Loader from 'contao-package-list/src/components/fragments/Loader';
    import LoadingButton from 'contao-package-list/src/components/fragments/LoadingButton';
    import Console from '../fragments/Console';
    import Checkbox from '../widgets/Checkbox';

    export default {
        components: { BoxedLayout, Loader, LoadingButton, Console, Checkbox },

        data: () => ({
            type: null,
            status: '',
            changes: null,
            hasDeletes: false,
            hash: null,
            withDeletes: true,

            showConsole: false,
            previousResult: true,
            checking: true,
            executing: false,
            closing: false,
        }),

        computed: {
            ...mapState('server/database', ['supported']),
            ...mapState(['setupStep']),
            ...mapGetters('server/database', ['hasChanges']),

            isEmpty: vm => vm.status !== 'active' && vm.operations && !vm.operations.length,
            isComplete: vm => vm.status === 'complete',
            hasError: vm => vm.status === 'error',
            hasProblem: vm => vm.type === 'problem' || vm.type === 'warning',

            description () {
                if (this.type === 'problem') {
                    return this.$tc('ui.migrate.problem', this.operations.length);
                }

                if (this.type === 'warning') {
                    return this.$t('ui.migrate.warning');
                }


                if (this.previousResult && this.hasChanges) {
                    return this.$t('ui.migrate.previousChanges');
                }

                if (this.previousResult) {
                    return this.$t('ui.migrate.previousComplete');
                }

                if (this.isComplete && this.hasChanges) {
                    return this.$t('ui.migrate.appliedChanges');
                }

                if (this.isComplete) {
                    return this.$t('ui.migrate.appliedComplete');
                }

                return this.$t('ui.migrate.error');
            },

            consoleTitle () {
                switch (this.type) {
                    case 'migrations':
                    case 'migrations-only':
                        return this.$t('ui.migrate.migrationTitle');

                    case 'schema':
                    case 'schema-only':
                        return this.$t('ui.migrate.schemaTitle');

                    case 'problem':
                        return this.$t('ui.migrate.problemTitle');

                    case 'warning':
                        return this.$t('ui.migrate.warningTitle');
                }

                return '';
            },

            console () {
                if (!this.changes || !this.changes.length) {
                    return '';
                }

                let console = ''
                this.changes.forEach((change) => {
                    console += `${change.name}\n`;
                });

                return console;
            },

            operations () {
                this.hasDeletes = false;

                if (!this.changes) {
                    return null;
                }

                if (this.hasProblem) {
                    return this.changes.map(change => ({
                        status: change.status,
                        summary: change.name,
                        console: change.message,
                    }));
                }

                if (this.type === 'migrations' || this.type === 'migrations-only') {
                    return this.changes.map(change => ({
                        status: change.status,
                        summary: change.name,
                        details: change.message,
                    }));
                }

                const operations = [];
                this.changes.forEach((change) => {
                    let result;

                    result = new RegExp('^CREATE TABLE ([^ ]+) .+$').exec(change.name);
                    if (result) {
                        operations.push({
                            status: change.status,
                            summary: this.$t('ui.migrate.addTable', { table: result[1] }),
                            console: change.name,
                        });
                        return;
                    }

                    result = new RegExp('^DROP TABLE (.+)$').exec(change.name);
                    if (result) {
                        operations.push({
                            status: this.withDeletes ? change.status : 'skipped',
                            summary: this.generateStatus(this.$t('ui.migrate.dropTable', { table: result[1] }), !this.withDeletes),
                            console: change.name,
                        });
                        this.hasDeletes = true;
                        return;
                    }

                    result = new RegExp('^CREATE INDEX ([^ ]+) ON ([^ ]+) \\(([^)]+)\\)$').exec(change.name);
                    if (result) {
                        operations.push({
                            status: change.status,
                            summary: this.$t('ui.migrate.createIndex', { name: result[1], table: result[2] }),
                            details: result[3],
                            console: change.name,
                        });
                        return;
                    }

                    result = new RegExp('^DROP INDEX ([^ ]+) ON ([^ ]+)$').exec(change.name);
                    if (result) {
                        operations.push({
                            status: this.withDeletes ? change.status : 'skipped',
                            summary: this.generateStatus(this.$t('ui.migrate.dropIndex', { name: result[1], table: result[2] }), !this.withDeletes),
                            details: result[3],
                            console: change.name,
                        });
                        this.hasDeletes = true;
                        return;
                    }

                    result = new RegExp('^ALTER TABLE ([^ ]+) (.+)$').exec(change.name);
                    if (result) {
                        const table = result[1];
                        const operation = {
                            status: change.status,
                            summary: [],
                            details: [],
                            console: change.name,
                        };

                        const ops = result[2].split(',').map(p => p.trim())
                        let deleteOps = 0;
                        ops.forEach((part) => {
                            let alter;
                            alter = new RegExp('^ADD ([^ ]+) (.+)$').exec(part);
                            if (alter) {
                                operation.summary.push(this.$t('ui.migrate.addField', { table, field: alter[1] }));
                                operation.details.push(alter[2]);
                                return;
                            }

                            alter = new RegExp('^CHANGE ([^ ]+) ([^ ]+) (.+)$').exec(part);
                            if (alter) {
                                operation.summary.push(this.$t('ui.migrate.changeField', { table, field: alter[1] }));
                                operation.details.push(alter[3]);
                                return;
                            }

                            alter = new RegExp('^DROP (.+)$').exec(part);
                            if (alter) {
                                operation.summary.push(this.generateStatus(this.$t('ui.migrate.dropField', { table, field: alter[1] }), !this.withDeletes));
                                operation.details.push('');
                                this.hasDeletes = true;
                                deleteOps++;
                                return;
                            }

                            operation.summary.push(part);
                            operation.details.push('');
                        })

                        if (deleteOps === ops.length) {
                            operation.status = this.withDeletes ? change.status : 'skipped'
                        }

                        operations.push(operation);
                        return;
                    }

                    operations.push({
                        status: change.status,
                        summary: change.name,
                        console: change.name,
                    });

                    // Unknown operation, assume it could be a DROP
                    this.hasDeletes = true;
                })

                return operations;
            },
        },

        methods: {
            async poll (response) {
                if (response.status === 201) {
                    return new Promise((resolve) => {
                        setTimeout(async () => {
                            await this.poll(await this.$http.get('api/contao/database-migration'));
                            resolve();
                        }, 1000);
                    });
                }

                const data = await response.json()

                if (!this.changes || data.status) {
                    this.type = data.type;
                    this.status = data.status;
                    this.hash = data.hash;
                    this.changes = data.operations;
                }

                if (!data.status || data.status === 'active') {
                    return new Promise((resolve) => {
                        setTimeout(async () => {
                            await this.poll(await this.$http.get('api/contao/database-migration'));
                            resolve();
                        }, 1000);
                    })
                }
            },

            async execute () {
                this.executing = true;

                await this.$http.put('api/contao/database-migration', {
                    type: this.type,
                    hash: this.hash,
                    withDeletes: this.withDeletes && this.hasDeletes,
                });

                setTimeout(async () => {
                    await this.poll(await this.$http.get('api/contao/database-migration'));
                    await this.$store.dispatch('server/database/get', false);
                    this.executing = false;
                }, 1000);
            },

            async check (skipWarnings = false) {
                this.checking = true;
                const type = this.type || this.$store.state.migrationsType;

                if (this.status) {
                    this.type = null;
                    this.status = '';
                    this.changes = null;
                    this.hash = null;

                    await this.$http.delete('api/contao/database-migration')
                }

                let response = await this.$http.get('api/contao/database-migration');

                if (response.status === 204) {
                    this.previousResult = false;
                    response = await this.$http.put('api/contao/database-migration', { type, skipWarnings });
                }

                await this.poll(response)
                await this.$http.delete('api/contao/database-migration')

                this.checking = false;
            },

            checkAll (skipWarnings = false) {
                this.type = null;
                this.check(skipWarnings);
            },

            generateStatus (label, strike) {
                return strike ? `~${label}~` : label;
            },

            async close () {
                this.closing = true;
                await this.$http.delete('api/contao/database-migration');
                await this.$store.dispatch('server/database/get', false);

                if (this.setupStep > 0) {
                    await this.$store.dispatch('server/adminUser/get', false);
                    this.$store.commit('setView', views.SETUP);
                } else {
                    this.$store.commit('setView', views.READY);
                }

                this.closing = false;
            },

            async setup () {
                this.$store.commit('setup', 3);
            },
        },

        mounted () {
            this.check()
        }
    }
</script>

<style lang="scss">
    @import "~contao-package-list/src/assets/styles/defaults";

    .database-migration {
        &__header {
            margin-left: auto;
            margin-right: auto;
            padding: 40px 0;
            text-align: center;
        }

        &__icon {
            background: $contao-color;
            border-radius: 10px;
            padding:10px;
        }

        &__headline {
            margin-top: .5em;
            margin-bottom: .5em;
            font-size: 36px;
            font-weight: $font-weight-light;
            line-height: 1;
        }

        &__description {
            margin: 0 50px;
            font-weight: $font-weight-bold;
        }

        &__actions {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            margin-top: 2em;
            padding: 0 50px;

            @include screen(960) {
                flex-direction: row;
            }
        }

        .widget-button {
            width: 250px;
            height: 35px;
            margin: 5px;
            padding: 0 30px;
            line-height: 35px;

            @include screen(960) {
                width: auto;
            }
        }

        &__main {
            margin: 0 50px 50px;
            background: #24292e;
        }

        &__loading {
            width: 30px;
            margin: 40px auto;

            .sk-circle {
                width: 30px;
                height: 30px;
            }
        }
    }
</style>
