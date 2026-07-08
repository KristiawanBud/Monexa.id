/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.vue',
    './resources/**/*.js',
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        brand: {
          DEFAULT: '#2F6BFF',
          dark: '#1E4FD6',
          light: '#EAF0FF',
        },
      },
      borderRadius: {
        xl2: '20px',
        xl3: '24px',
      },
      boxShadow: {
        soft: '0 4px 20px rgba(15,23,42,0.06)',
        card: '0 2px 12px rgba(15,23,42,0.05)',
      },
    },
  },
  corePlugins: {
    // Preflight dimatikan supaya CSS Tailwind TIDAK bentrok/reset
    // styling halaman lain yang belum migrasi ke Tailwind.
    preflight: false,
  },
  plugins: [],
}
