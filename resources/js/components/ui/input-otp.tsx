"use client"

import * as React from "react"
import { OTPInput, OTPInputContext } from "input-otp"
import type { OTPInputProps } from "input-otp"

import { cn } from "@/lib/utils"
import { MinusIcon } from "lucide-react"

type InputOTPProps = Omit<OTPInputProps, "size"> & {
  containerClassName?: string
  size?: "sm" | "default" | "comfortable"
}

function InputOTP({
  className,
  containerClassName,
  size = "default",
  ...props
}: InputOTPProps) {
  const isInvalid = props["aria-invalid"] === true || props["aria-invalid"] === "true"

  const otpInputProps = {
    ...props,
    containerClassName: cn(
      "cn-input-otp flex items-center has-disabled:opacity-50",
      containerClassName
    ),
    spellCheck: false,
    className: cn("disabled:cursor-not-allowed", className),
  } as OTPInputProps

  return (
    <div
      data-slot="input-otp-root"
      data-invalid={isInvalid || undefined}
      data-size={size}
      className="group/input-otp"
    >
      <OTPInput {...otpInputProps} />
    </div>
  )
}

function InputOTPGroup({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="input-otp-group"
      className={cn(
        "flex items-center rounded-lg group-data-[invalid=true]/input-otp:ring-3 group-data-[invalid=true]/input-otp:ring-destructive/20 dark:group-data-[invalid=true]/input-otp:ring-destructive/40",
        className
      )}
      {...props}
    />
  )
}

function InputOTPSlot({
  index,
  className,
  ...props
}: React.ComponentProps<"div"> & {
  index: number
}) {
  const inputOTPContext = React.useContext(OTPInputContext)
  const { char, hasFakeCaret, isActive } = inputOTPContext?.slots[index] ?? {}

  return (
    <div
      data-slot="input-otp-slot"
      data-active={isActive}
      className={cn(
        "relative flex size-8 items-center justify-center border-y border-r border-input text-sm transition-all outline-none first:rounded-l-lg first:border-l last:rounded-r-lg group-data-[invalid=true]/input-otp:border-destructive data-[active=true]:z-10 data-[active=true]:border-ring data-[active=true]:ring-3 data-[active=true]:ring-ring/50 group-data-[invalid=true]/input-otp:data-[active=true]:border-destructive group-data-[invalid=true]/input-otp:data-[active=true]:ring-destructive/20 group-data-[size=sm]/input-otp:h-7 group-data-[size=sm]/input-otp:text-xs group-data-[size=default]/input-otp:h-8 group-data-[size=comfortable]/input-otp:h-9 group-data-[size=comfortable]/input-otp:text-base dark:bg-input/30 dark:group-data-[invalid=true]/input-otp:data-[active=true]:ring-destructive/40",
        className
      )}
      {...props}
    >
      {char}
      {hasFakeCaret && (
        <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
          <div className="h-4 w-px animate-caret-blink bg-foreground duration-1000" />
        </div>
      )}
    </div>
  )
}

function InputOTPSeparator({ ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="input-otp-separator"
      className="flex items-center [&_svg:not([class*='size-'])]:size-4"
      role="separator"
      {...props}
    >
      <MinusIcon
      />
    </div>
  )
}

export { InputOTP, InputOTPGroup, InputOTPSlot, InputOTPSeparator }
