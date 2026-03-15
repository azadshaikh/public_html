import { AlertTriangleIcon } from "lucide-react"
import * as React from "react"

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { cn } from "@/lib/utils"

type ConfirmationDialogTone = "default" | "destructive"

type ConfirmationDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  title: React.ReactNode
  description?: React.ReactNode
  confirmLabel: React.ReactNode
  cancelLabel?: React.ReactNode
  onConfirm?: () => void
  onCancel?: () => void
  icon?: React.ReactNode
  tone?: ConfirmationDialogTone
  confirmVariant?: React.ComponentProps<typeof AlertDialogAction>["variant"]
  confirmDisabled?: boolean
  cancelDisabled?: boolean
  contentClassName?: string
  confirmClassName?: string
  cancelClassName?: string
}

const toneClassNames: Record<ConfirmationDialogTone, string> = {
  default: "bg-primary/10 text-primary",
  destructive: "bg-destructive text-white",
}

function ConfirmationDialog({
  open,
  onOpenChange,
  title,
  description,
  confirmLabel,
  cancelLabel = "Cancel",
  onConfirm,
  onCancel,
  icon,
  tone = "destructive",
  confirmVariant = "default",
  confirmDisabled = false,
  cancelDisabled = false,
  contentClassName,
  confirmClassName,
  cancelClassName,
}: ConfirmationDialogProps) {
  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent
        size="comfortable"
        className={cn(
          "w-[min(calc(100%-2rem),36rem)] max-w-[36rem] gap-0 overflow-hidden rounded-2xl p-0",
          contentClassName
        )}
      >
        <div className="px-6 pt-5 pb-4">
          <div className="flex flex-col items-center text-center">
            <div className="flex items-center justify-center gap-2.5">
              <div
                className={cn(
                  "flex size-7 shrink-0 items-center justify-center rounded-full",
                  toneClassNames[tone]
                )}
              >
                {icon ?? <AlertTriangleIcon className="size-4 " />}
              </div>
              <AlertDialogTitle className="pt-0.5 text-[1.7rem] leading-none font-semibold tracking-tight">
                {title}
              </AlertDialogTitle>
            </div>
            {description ? (
              <AlertDialogDescription className="mt-3 w-full max-w-none text-[1.02rem] leading-6 text-muted-foreground text-pretty">
                {description}
              </AlertDialogDescription>
            ) : null}
          </div>
        </div>

        <div className="grid grid-cols-2 border-t">
          <AlertDialogCancel
            variant="ghost"
            size="comfortable"
            disabled={cancelDisabled}
            onClick={onCancel}
            className={cn(
              "h-[2.875rem] rounded-none border-0 border-r px-5 text-[0.95rem] font-medium shadow-none hover:bg-muted/50",
              cancelClassName
            )}
          >
            {cancelLabel}
          </AlertDialogCancel>
          <AlertDialogAction
            variant={confirmVariant}
            size="comfortable"
            disabled={confirmDisabled}
            onClick={onConfirm}
            className={cn(
              "h-[2.875rem] rounded-none px-5 text-[0.95rem] font-medium shadow-none",
              confirmClassName
            )}
          >
            {confirmLabel}
          </AlertDialogAction>
        </div>
      </AlertDialogContent>
    </AlertDialog>
  )
}

export { ConfirmationDialog }
