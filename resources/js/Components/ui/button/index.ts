import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

export const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-xl text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:ring-offset-1 focus-visible:ring-offset-background disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0",
  {
    variants: {
      variant: {
        default: "bg-brand-button text-brand-button-fg shadow-sm hover:bg-brand-button/90",
        destructive:
          "bg-destructive text-destructive-foreground shadow-sm hover:bg-destructive/90",
        outline:
          "border border-input bg-background hover:border-slate-300 hover:bg-slate-50 dark:hover:bg-white/[0.06]",
        secondary:
          "bg-secondary text-secondary-foreground hover:bg-slate-100 dark:hover:bg-white/10",
        ghost: "hover:bg-brand-tint hover:text-brand-primary dark:hover:bg-white/[0.06] dark:hover:text-zinc-50",
        link: "text-primary underline-offset-4 hover:underline",
      },
      size: {
        "default": "h-10 px-4 py-2",
        "xs": "h-7 rounded-lg px-2",
        "sm": "h-8 rounded-lg px-3 text-xs",
        "lg": "h-11 px-6",
        "icon": "h-10 w-10",
        "icon-sm": "size-8",
        "icon-lg": "size-10",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
)

export type ButtonVariants = VariantProps<typeof buttonVariants>
