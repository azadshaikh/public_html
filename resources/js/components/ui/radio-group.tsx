import * as React from "react"
import { RadioGroup as RadioGroupPrimitive } from "radix-ui"

import { cn } from "@/lib/utils"

function RadioGroup({
  className,
  ...props
}: React.ComponentProps<typeof RadioGroupPrimitive.Root>) {
  return (
    <RadioGroupPrimitive.Root
      data-slot="radio-group"
      className={cn("grid w-full gap-2", className)}
      {...props}
    />
  )
}

function RadioGroupItem({
  className,
  size = "comfortable",
  ...props
}: React.ComponentProps<typeof RadioGroupPrimitive.Item> & {
  size?: "sm" | "default" | "comfortable"
}) {
  return (
    <RadioGroupPrimitive.Item
      data-slot="radio-group-item"
      data-size={size}
      className={cn(
        "group/radio-group-item peer relative flex aspect-square shrink-0 rounded-full border border-input outline-none after:absolute after:-inset-x-3 after:-inset-y-2 focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-2 aria-invalid:ring-destructive/15 aria-invalid:aria-checked:border-primary dark:bg-input/30 dark:aria-invalid:border-destructive/50 dark:aria-invalid:ring-destructive/40 data-checked:border-primary data-checked:bg-primary data-checked:text-primary-foreground dark:data-checked:bg-primary",
        "data-[size=sm]:size-3.5",
        "data-[size=default]:size-4",
        "data-[size=comfortable]:size-4.5",
        className
      )}
      {...props}
    >
      <RadioGroupPrimitive.Indicator
        data-slot="radio-group-indicator"
        className="flex items-center justify-center data-[size=sm]:size-3.5 data-[size=default]:size-4 data-[size=comfortable]:size-5"
        data-size={size}
      >
        <span className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-primary-foreground group-data-[size=sm]/radio-group-item:size-1.5 group-data-[size=default]/radio-group-item:size-2 group-data-[size=comfortable]/radio-group-item:size-2.5" />
      </RadioGroupPrimitive.Indicator>
    </RadioGroupPrimitive.Item>
  )
}

export { RadioGroup, RadioGroupItem }
