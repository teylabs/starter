import stylistic from '@stylistic/eslint-plugin';
import {
    configureVueProject,
    defineConfigWithVueTs,
    vueTsConfigs,
} from '@vue/eslint-config-typescript';
import prettier from 'eslint-config-prettier/flat';
import importPlugin from 'eslint-plugin-import';
import vue from 'eslint-plugin-vue';

configureVueProject({ scriptLangs: ['js', 'ts'] });

const controlStatements = [
    'if',
    'return',
    'for',
    'while',
    'do',
    'switch',
    'try',
    'throw',
];
const paddingAroundControl = [
    ...controlStatements.flatMap((stmt) => [
        { blankLine: 'always', prev: '*', next: stmt },
        { blankLine: 'always', prev: stmt, next: '*' },
    ]),
];

export default defineConfigWithVueTs(
    vue.configs['flat/essential'],
    vueTsConfigs.recommended,
    {
        plugins: {
            import: importPlugin,
        },
        settings: {
            'import/resolver': {
                typescript: {
                    alwaysTryTypes: true,
                    project: './tsconfig.json',
                },
                node: true,
            },
        },
        rules: {
            'vue/multi-word-component-names': 'off',
            // Misreads TypeScript unions in template expressions as Vue 2 filters.
            'vue/no-deprecated-filter': 'off',
            'no-restricted-imports': [
                'error',
                {
                    paths: [
                        {
                            name: '@headlessui/vue',
                            message:
                                'Use the kit Reka-based controls.',
                        },
                    ],
                },
            ],
            '@typescript-eslint/no-explicit-any': 'off',
            '@typescript-eslint/consistent-type-imports': [
                'error',
                {
                    prefer: 'type-imports',
                    fixStyle: 'separate-type-imports',
                    disallowTypeAnnotations: false,
                },
            ],
            // import/order stays off: it crashes on this config.
            'import/order': 'off',
            'import/consistent-type-specifier-style': [
                'error',
                'prefer-top-level',
            ],
        },
    },
    {
        plugins: {
            '@stylistic': stylistic,
        },
        rules: {
            '@stylistic/brace-style': [
                'error',
                '1tbs',
                { allowSingleLine: false },
            ],
            '@stylistic/padding-line-between-statements': [
                'error',
                ...paddingAroundControl,
            ],
        },
    },
    {
        ignores: [
            '**/vendor',
            'node_modules',
            'public',
            'bootstrap/ssr',
            'tailwind.config.js',
            'vite.config.ts',
            // Node's built-in test runner files (npm run test:js): run by Node, not bundled or type-checked by vue-tsc.
            'tests/js',
            // Kit-owned, shadcn-vue primitives and generated types: .prettierignore lists the same paths.
            'resources/js/components/Kit/**',
            'resources/js/pages/Auth/Invitation/**',
            'resources/js/pages/Auth/Login/**',
            'resources/js/pages/Auth/TwoFactor/**',
            'resources/js/pages/Auth/VerifyEmail/**',
            'resources/js/pages/Provision/**',
            'resources/js/pages/Welcome/**',
            'resources/js/pages/Workspace/Account/**',
            'resources/js/pages/Workspace/Dashboard/**',
            'resources/js/pages/Workspace/Error/**',
            'resources/js/pages/Workspace/Index/**',
            'resources/js/pages/Workspace/Layout.vue',
            'resources/js/pages/Workspace/Notes/**',
            'resources/js/pages/Workspace/Partials/**',
            'resources/js/pages/Workspace/Placeholder/**',
            'resources/js/pages/Workspace/Search/**',
            'resources/js/pages/Workspace/Sections/**',
            'resources/js/pages/Workspace/Settings/Connections/**',
            'resources/js/pages/Workspace/Settings/General/**',
            'resources/js/pages/Workspace/Settings/Layout.vue',
            'resources/js/pages/Workspace/Settings/People/**',
            'resources/js/pages/Workspace/Settings/Placeholder/**',
            'resources/js/types/kit-shell.d.ts',
            'resources/js/widgets/Notes.vue',
            'resources/js/components/ui/**',
            'resources/js/types.generated.d.ts',
        ],
    },
    prettier,
    {
        plugins: {
            '@stylistic': stylistic,
        },
        rules: {
            curly: ['error', 'all'],
            '@stylistic/brace-style': [
                'error',
                '1tbs',
                { allowSingleLine: false },
            ],
        },
    },
);
