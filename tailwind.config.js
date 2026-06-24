/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/**/*.php',
    ],
    theme: {
        extend: {
            colors: {
                theme: {
                    background: 'var(--theme-background, #f8fafc)',
                    text: 'var(--theme-text, #1f2937)',
                    heading: 'var(--theme-heading, #111827)',
                    primary: 'var(--theme-primary, #2563eb)',
                    'primary-hover': 'var(--theme-primary-hover, #1d4ed8)',
                    accent: 'var(--theme-accent, #0f766e)',
                    secondary: 'var(--theme-secondary, #111827)',
                },
            },
            borderRadius: {
                theme: 'var(--theme-button-radius, 6px)',
            },
            maxWidth: {
                theme: 'var(--theme-container-width, 1180px)',
            },
            fontFamily: {
                theme: 'var(--theme-font-family, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif)',
            },
        },
    },
    corePlugins: {
        preflight: false,
    },
    plugins: [],
};
