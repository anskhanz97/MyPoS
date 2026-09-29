import eslint from "@eslint/js";
import globals from "globals";
import tseslint from "typescript-eslint";
import vue from "eslint-plugin-vue";

export default tseslint.config(
    { ignores: ["public/build/**", "vendor/**", "node_modules/**"] },
    eslint.configs.recommended,
    ...tseslint.configs.recommended,
    ...vue.configs["flat/recommended"],
    {
        languageOptions: {
            globals: globals.browser,
            parserOptions: { parser: tseslint.parser },
        },
        rules: {
            "vue/html-indent": "off",
            "vue/html-closing-bracket-newline": "off",
            "vue/html-self-closing": "off",
            "vue/max-attributes-per-line": "off",
            "vue/multiline-html-element-content-newline": "off",
            "vue/multi-word-component-names": "off",
            "vue/singleline-html-element-content-newline": "off",
        },
    },
);
