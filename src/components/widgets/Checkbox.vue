<template>
    <div class="widget widget-checkbox" :class="{ 'widget--required': required }">
        <input
            ref="input"
            type="checkbox"
            :id="label ? 'ctrl_'+name : ''"
            :name="name"
            :disabled="disabled"
            :required="required"
            :checked="value"
            @change="toggle($event.target.checked)"
        >
        <label v-if="label" :for="'ctrl_'+name">{{ label }}</label>
    </div>
</template>

<script>
    export default {
        props: {
            name: {
                type: String,
                required: true,
            },
            label: {
                type: String,
                required: true,
            },
            value: Boolean,
            disabled: Boolean,
            required: Boolean,
        },
        methods: {
            toggle(value) {
                this.$emit('input', !!value);
            },
        },
    };
</script>

<style lang="scss">
    .widget-checkbox {
        input {
            position: absolute;
            visibility: hidden;
        }

        label {
            display: block;
            padding-left: 25px;
            background: url("../../assets/images/widget-checkbox--off.svg") 0 1px no-repeat;
            background-size: 20px 20px;
            text-align: left;
        }

        input:checked + label {
            background-image: url("../../assets/images/widget-checkbox--on.svg");
        }

        input:disabled + label {
            opacity: .5;
        }
    }
</style>
