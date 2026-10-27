// tailwind.config.js
import defaultTheme from "tailwindcss/defaultTheme"
import forms from "@tailwindcss/forms"

/*
 * Paleta "Violeta" del ERP.
 * Los grises de Tailwind (slate, gray, zinc, neutral) se redefinen con una escala
 * neutra con un leve tinte violeta: así todas las pantallas que usan clases fijas
 * adoptan el tono sin reescribirlas.
 * - inkLight (slate, gray): pensada para el modo claro (500 = tinta secundaria AA sobre blanco).
 * - inkDark (zinc, neutral): en el código se usan casi solo con `dark:`; sus tonos
 *   medios son más claros para conservar contraste AA sobre el fondo oscuro.
 * indigo y violet apuntan a la escala de marca (#6419FF / #AA81FE).
 */
const inkLight = {
  50: "#F7F6FB",
  100: "#EFEDF6",
  200: "#E6E3F0",
  300: "#D3CFE1",
  400: "#9D98B2",
  500: "#6B6880",
  600: "#56526C",
  700: "#403C55",
  800: "#2B2740",
  900: "#1C1830",
  950: "#141120",
}

const inkDark = {
  50: "#F7F6FB",
  100: "#F1EFF8",
  200: "#DEDAEA",
  300: "#C4BFD6",
  400: "#A9A4BD",
  500: "#8A86A0",
  600: "#625E7A",
  700: "#3D3856",
  800: "#2E2945",
  900: "#1C1830",
  950: "#141120",
}

const violet = {
  50: "#F6F2FF",
  100: "#EEE8FF",
  200: "#DDD0FF",
  300: "#CCB2FF",
  400: "#AA81FE",
  500: "#7A3BFF",
  600: "#6419FF",
  700: "#5212D6",
  800: "#420FAD",
  900: "#320C82",
  950: "#1F0752",
}

/** @type {import('tailwindcss').Config} */
export default {
  darkMode: "class", // <- correcto
  content: [
    "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
    "./storage/framework/views/*.php",
    "./resources/views/**/*.blade.php",
    "./resources/js/**/*.vue",
    "./resources/js/**/*.ts",
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ["Onest", "system-ui", ...defaultTheme.fontFamily.sans],
      },
      // Jerarquía más sobria: los pesos 800/900 se muestran como 700.
      fontWeight: {
        extrabold: "700",
        black: "700",
      },
      borderRadius: {
        lg: "var(--radius)",
        md: "calc(var(--radius) - 2px)",
        sm: "calc(var(--radius) - 4px)",
        "3xl": "1.25rem",
      },
      boxShadow: {
        // Sombras con tinte violeta para elementos elevados (menús, modales)
        "2xl": "0 24px 48px -16px rgb(40 20 90 / 0.22), 0 8px 16px -8px rgb(40 20 90 / 0.10)",
        xl: "0 16px 32px -12px rgb(40 20 90 / 0.18), 0 4px 10px -4px rgb(40 20 90 / 0.08)",
        lg: "0 10px 24px -10px rgb(40 20 90 / 0.16), 0 2px 6px -2px rgb(40 20 90 / 0.06)",
        md: "0 4px 12px -4px rgb(40 20 90 / 0.10), 0 1px 3px rgb(40 20 90 / 0.05)",
        sm: "0 1px 2px rgb(40 20 90 / 0.05)",
        DEFAULT: "0 1px 3px rgb(40 20 90 / 0.06), 0 1px 2px rgb(40 20 90 / 0.04)",
      },
      colors: {
        slate: inkLight,
        gray: inkLight,
        zinc: inkDark,
        neutral: inkDark,
        indigo: violet,
        violet,
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
          // Tinte de selección y degradado de marca (fijos)
          tint: "rgb(var(--brand-tint) / <alpha-value>)",
          soft: "#AA81FE",
          ink: "#1C1830",
        },
        border: "hsl(var(--border))",
        input: "hsl(var(--input))",
        ring: "hsl(var(--ring))",
      },
    },
  },
  plugins: [forms, require("tailwindcss-animate")],
}
