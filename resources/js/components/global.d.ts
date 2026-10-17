export {};

declare module "vue" {
    export interface GlobalComponents {
        AppSelect: (typeof import("./AppSelect.vue"))["default"];
        AppDatePicker: (typeof import("./AppDatePicker.vue"))["default"];
    }
}
