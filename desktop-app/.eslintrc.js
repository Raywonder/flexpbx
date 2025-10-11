module.exports = {
    env: {
        browser: true,
        commonjs: true,
        es6: true,
        node: true
    },
    extends: 'eslint:recommended',
    globals: {
        Atomics: 'readonly',
        SharedArrayBuffer: 'readonly'
    },
    parserOptions: {
        ecmaVersion: 2020,
        sourceType: 'module'
    },
    rules: {
        'no-console': 'off',
        'no-unused-vars': 'warn',
        'no-undef': 'error',
        'no-redeclare': 'error',
        'prefer-const': 'warn',
        'no-var': 'warn'
    }
};