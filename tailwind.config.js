// tailwind.config.js
import defaultTheme from "tailwindcss/defaultTheme"
import forms from "@tailwindcss/forms"

/** @type {import('tailwindcss').Config} */
export default {
  darkMode: "class", // <- correcto
  content: [
    "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
    "./storage/framework/views/*.php",
    "./resources/views/**/*.blade.php",
    "./resources/js/**/*.vue",
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ["Figtree", ...defaultTheme.fontFamily.sans],
      },
      borderRadius: {
        lg: "var(--radius)",
        md: "calc(var(--radius) - 2px)",
        sm: "calc(var(--radius) - 4px)",
      },
      colors: {
        background: "hsl(var(--background))",
        foreground: "hsl(var(--foreground))",
        card: {
          DEFAULT: "hsl(var(--card))",
          foreground: "hsl(var(--card-foreground))",
        },
        popover: {
          DEFAULT: "hsl(var(--popover))",
          foreground: "hsl(var(--popover-foreground))",
        },
        primary: {
          DEFAULT: "hsl(var(--primary))",
          foreground: "hsl(var(--primary-foreground))",
        },
        secondary: {
          DEFAULT: "hsl(var(--secondary))",
          foreground: "hsl(var(--secondary-foreground))",
        },
        muted: {
          DEFAULT: "hsl(var(--muted))",
          foreground: "hsl(var(--muted-foreground))",
        },
        accent: {
          DEFAULT: "hsl(var(--accent))",
          foreground: "hsl(var(--accent-foreground))",
        },
        destructive: {
          DEFAULT: "hsl(var(--destructive))",
          foreground: "hsl(var(--destructive-foreground))",
        },
        // Colores de marca configurables (módulo Configuración)
        brand: {
          primary: { DEFAULT: "rgb(var(--brand-primary) / <alpha-value>)", fg: "rgb(var(--brand-primary-fg) / <alpha-value>)" },
          accent: { DEFAULT: "rgb(var(--brand-accent) / <alpha-value>)", fg: "rgb(var(--brand-accent-fg) / <alpha-value>)" },
          button: { DEFAULT: "rgb(var(--brand-button) / <alpha-value>)", fg: "rgb(var(--brand-button-fg) / <alpha-value>)" },
          success: { DEFAULT: "rgb(var(--brand-success) / <alpha-value>)", fg: "rgb(var(--brand-success-fg) / <alpha-value>)" },
          warning: { DEFAULT: "rgb(var(--brand-warning) / <alpha-value>)", fg: "rgb(var(--brand-warning-fg) / <alpha-value>)" },
          danger: { DEFAULT: "rgb(var(--brand-danger) / <alpha-value>)", fg: "rgb(var(--brand-danger-fg) / <alpha-value>)" },
        },
        border: "hsl(var(--border))",
        input: "hsl(var(--input))",
        ring: "hsl(var(--ring))",
      },
    },
  },
  plugins: [forms, require("tailwindcss-animate")],
}
