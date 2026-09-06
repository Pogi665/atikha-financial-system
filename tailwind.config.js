/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.php",
    "./**/*.php"
  ],
  theme: {
    extend: {
      keyframes: {
        'fade-slide-up': {
          '0%': { opacity: '0', transform: 'translateY(20px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        }
      },
      animation: {
        'fade-slide-up': 'fade-slide-up 0.6s ease-out forwards',
      }
    },
  },
  plugins: [],
}