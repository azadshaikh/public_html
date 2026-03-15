import { AlertTriangleIcon } from "lucide-react"
import * as React from "react"

import {
  AlertDialog,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"

type ConfirmationDialogTone = "default" | "destructive"

type ConfirmationDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  title?: React.ReactNode
  description?: React.ReactNode
  confirmLabel?: React.ReactNode
  cancelLabel?: React.ReactNode
  onConfirm?: () => void
  onCancel?: () => void
  icon?: React.ReactNode
  tone?: ConfirmationDialogTone
  confirmVariant?: React.ComponentProps<typeof Button>["variant"]
  confirmDisabled?: boolean
  cancelDisabled?: boolean
  contentClassName?: string
  confirmClassName?: string
  cancelClassName?: string
}

type ConfirmationDialogSnapshot = Pick<
  ConfirmationDialogProps,
  | "title"
  | "description"
  | "confirmLabel"
  | "cancelLabel"
  | "icon"
  | "tone"
  | "confirmVariant"
  | "confirmClassName"
  | "cancelClassName"
>

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
  const [snapshot, setSnapshot] = React.useState<ConfirmationDialogSnapshot>({
    title,
    description,
    confirmLabel,
    cancelLabel,
    icon,
    tone,
    confirmVariant,
    confirmClassName,
    cancelClassName,
  })

  React.useEffect(() => {
    if (!open) {
      return
    }

    setSnapshot({
      title,
      description,
      confirmLabel,
      cancelLabel,
      icon,
      tone,
      confirmVariant,
      confirmClassName,
      cancelClassName,
    })
  }, [
    cancelClassName,
    cancelLabel,
    confirmClassName,
    confirmLabel,
    confirmVariant,
    description,
    icon,
    open,
    title,
    tone,
  ])

  const resolvedTitle = open ? title : snapshot.title
  const resolvedDescription = open ? description : snapshot.description
  const resolvedConfirmLabel = open ? confirmLabel : snapshot.confirmLabel
  const resolvedCancelLabel = open ? cancelLabel : snapshot.cancelLabel
  const resolvedIcon = open ? icon : snapshot.icon
  const resolvedTone = open ? tone : snapshot.tone
  const resolvedConfirmVariant = open
    ? confirmVariant
    : snapshot.confirmVariant
  const resolvedConfirmClassName = open
    ? confirmClassName
    : snapshot.confirmClassName
  const resolvedCancelClassName = open
    ? cancelClassName
    : snapshot.cancelClassName

  const handleCancel = React.useCallback(() => {
    onOpenChange(false)
    onCancel?.()
  }, [onCancel, onOpenChange])

  const handleConfirm = React.useCallback(() => {
    onOpenChange(false)
    onConfirm?.()
  }, [onConfirm, onOpenChange])

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
                  toneClassNames[resolvedTone ?? "destructive"]
                )}
              >
                {resolvedIcon ?? <AlertTriangleIcon className="size-4" />}
              </div>
              <AlertDialogTitle className="pt-0.5 text-[1.7rem] leading-none font-semibold tracking-tight">
                {resolvedTitle}
              </AlertDialogTitle>
            </div>
            {resolvedDescription ? (
              <AlertDialogDescription className="mt-3 w-full max-w-none text-[1.02rem] leading-6 text-muted-foreground text-pretty">
                {resolvedDescription}
              </AlertDialogDescription>
            ) : null}
          </div>
        </div>

        <div className="grid grid-cols-2 border-t">
          <Button
            type="button"
            variant="ghost"
            size="comfortable"
            disabled={cancelDisabled}
            onClick={handleCancel}
            className={cn(
              "h-[2.875rem] rounded-none border-0 border-r px-5 text-[0.95rem] font-medium shadow-none hover:bg-muted/50",
              resolvedCancelClassName
            )}
          >
            {resolvedCancelLabel ?? "Cancel"}
          </Button>
          <Button
            type="button"
            variant={resolvedConfirmVariant ?? "default"}
            size="comfortable"
            disabled={confirmDisabled}
            onClick={handleConfirm}
            className={cn(
              "h-[2.875rem] rounded-none px-5 text-[0.95rem] font-medium shadow-none",
              resolvedConfirmClassName
            )}
          >
            {resolvedConfirmLabel ?? "Confirm"}
          </Button>
        </div>
      </AlertDialogContent>
    </AlertDialog>
  )
}

export { ConfirmationDialog }
