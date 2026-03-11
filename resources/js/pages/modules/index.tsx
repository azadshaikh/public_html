import { Head, Link, router } from '@inertiajs/react'
import { ArrowLeftIcon, BoxesIcon, CheckCircle2Icon, PackageIcon, SearchIcon, ShieldAlertIcon, SortAscIcon, ZapIcon } from 'lucide-react'
import { useMemo, useState } from 'react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  CardFooter,
} from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import AppLayout from '@/layouts/app-layout'
import { dashboard } from '@/routes/index'
import type { BreadcrumbItem, ManagedModule } from '@/types'

type ModuleManagementPageProps = {
  managedModules: ManagedModule[]
  status?: string
  error?: string
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: dashboard(),
  },
  {
    title: 'Modules',
    href: '/modules',
  },
]

const sortOptions = {
  name: 'Sort by name',
  status: 'Sort by status',
  version: 'Sort by version',
} as const

export default function ModulesIndex({ managedModules, status, error }: ModuleManagementPageProps) {
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState<'all' | 'enabled' | 'disabled'>('all')
  const [sortBy, setSortBy] = useState<keyof typeof sortOptions>('name')
  const [processingModule, setProcessingModule] = useState<string | null>(null)
  const [moduleStatuses, setModuleStatuses] = useState<Record<string, 'enabled' | 'disabled'>>(
    Object.fromEntries(
      managedModules.map((module) => [module.name, module.status]),
    ) as Record<string, 'enabled' | 'disabled'>,
  )

  const modules = useMemo(() => {
    return managedModules
      .map((module) => {
        const currentStatus = moduleStatuses[module.name] ?? module.status

        return {
          ...module,
          status: currentStatus,
          enabled: currentStatus === 'enabled',
        }
      })
      .filter((module) => {
        if (statusFilter !== 'all' && module.status !== statusFilter) {
          return false
        }

        const query = search.trim().toLowerCase()

        if (query === '') {
          return true
        }

        return [module.name, module.version, module.description]
          .join(' ')
          .toLowerCase()
          .includes(query)
      })
      .sort((left, right) => {
        switch (sortBy) {
          case 'status':
            return left.status.localeCompare(right.status) || left.name.localeCompare(right.name)
          case 'version':
            return right.version.localeCompare(left.version) || left.name.localeCompare(right.name)
          default:
            return left.name.localeCompare(right.name)
        }
      })
  }, [managedModules, moduleStatuses, search, sortBy, statusFilter])

  const enabledCount = Object.values(moduleStatuses).filter((value) => value === 'enabled').length
  const disabledCount = managedModules.length - enabledCount

  function updateModuleStatus(moduleName: string, enabled: boolean) {
    const nextStatuses: Record<string, 'enabled' | 'disabled'> = {
      ...moduleStatuses,
      [moduleName]: enabled ? 'enabled' : 'disabled',
    }

    setModuleStatuses(nextStatuses)
    setProcessingModule(moduleName)

    router.patch('/modules', {
      modules: nextStatuses,
    }, {
      preserveScroll: true,
      onError: () => {
        setModuleStatuses(moduleStatuses)
      },
      onFinish: () => {
        setProcessingModule(null)
      },
    })
  }

  return (
    <AppLayout
      breadcrumbs={breadcrumbs}
      title="Manage Modules"
      description="Enable, disable, and browse local modules from one control center."
      headerActions={
        <div className="flex flex-wrap gap-3">
          <Button asChild variant="outline">
            <Link href={dashboard()}>
              <ArrowLeftIcon />
              Back to dashboard
            </Link>
          </Button>
        </div>
      }
    >
      <Head title="Modules" />

      <section className="space-y-6">
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          {[
            {
              label: 'Total modules',
              value: managedModules.length,
              icon: BoxesIcon,
              tone: 'bg-slate-100 text-slate-700 dark:bg-slate-900/60 dark:text-slate-200',
            },
            {
              label: 'Enabled',
              value: enabledCount,
              icon: CheckCircle2Icon,
              tone: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
            },
            {
              label: 'Disabled',
              value: disabledCount,
              icon: ShieldAlertIcon,
              tone: 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300',
            },
            {
              label: 'Updates available',
              value: 0,
              icon: ZapIcon,
              tone: 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
            },
          ].map((item) => {
            const Icon = item.icon

            return (
              <Card key={item.label} className="gap-3 border-0 bg-background py-0 shadow-sm ring-1 ring-black/5 dark:ring-white/10">
                <CardContent className="flex items-center gap-4 px-5 py-5">
                  <div className={`flex size-11 items-center justify-center rounded-xl ${item.tone}`}>
                    <Icon className="size-5" />
                  </div>
                  <div className="space-y-1">
                    <p className="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">{item.label}</p>
                    <p className="text-2xl font-semibold tracking-tight">{item.value}</p>
                  </div>
                </CardContent>
              </Card>
            )
          })}
        </div>

        <Card className="border-0 bg-background py-0 shadow-sm ring-1 ring-black/5 dark:ring-white/10">
          <CardContent className="grid gap-3 px-5 py-5 md:grid-cols-[minmax(0,1.4fr)_200px_200px]">
            <div className="relative">
              <SearchIcon className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search modules..."
                className="h-10 rounded-xl pl-9"
              />
            </div>

            <Select value={statusFilter} onValueChange={(value) => setStatusFilter(value as 'all' | 'enabled' | 'disabled')}>
              <SelectTrigger className="h-10 w-full rounded-xl px-3">
                <SelectValue placeholder="All statuses" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All modules</SelectItem>
                <SelectItem value="enabled">Enabled only</SelectItem>
                <SelectItem value="disabled">Disabled only</SelectItem>
              </SelectContent>
            </Select>

            <Select value={sortBy} onValueChange={(value) => setSortBy(value as keyof typeof sortOptions)}>
              <SelectTrigger className="h-10 w-full rounded-xl px-3">
                <SortAscIcon className="size-4 text-muted-foreground" />
                <SelectValue placeholder="Sort modules" />
              </SelectTrigger>
              <SelectContent>
                {Object.entries(sortOptions).map(([value, label]) => (
                  <SelectItem key={value} value={value}>{label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </CardContent>
        </Card>
      </section>

      {status ? (
        <Alert className="mt-6">
          <PackageIcon className="size-4" />
          <AlertTitle>Saved</AlertTitle>
          <AlertDescription>{status}</AlertDescription>
        </Alert>
      ) : null}

      {error ? (
        <Alert className="mt-4 border-destructive/30 text-destructive dark:border-destructive/40">
          <ShieldAlertIcon className="size-4" />
          <AlertTitle>Unavailable</AlertTitle>
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      ) : null}

      <section className="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        {modules.map((module) => {
          const isProcessing = processingModule === module.name
          const actionLabel = module.enabled ? 'Disable' : 'Enable'
          const actionVariant = module.enabled ? 'destructive' : 'outline'
          const statusTone = module.enabled
            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
            : 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300'

          return (
            <Card key={module.name} className="overflow-hidden border-0 bg-background py-0 shadow-sm ring-1 ring-black/5 dark:ring-white/10">
              <div className={`flex h-16 items-center justify-center ${module.enabled ? 'bg-linear-to-r from-emerald-500 to-green-500 text-white' : 'bg-linear-to-r from-slate-400 to-slate-500 text-white'}`}>
                <PackageIcon className="size-7" />
              </div>
              <CardHeader className="space-y-3 px-5 pt-5">
                <div className="flex items-start justify-between gap-4">
                  <div className="space-y-1">
                    <CardTitle className="text-xl">{module.name}</CardTitle>
                    <CardDescription className="line-clamp-2 min-h-10">
                      {module.description || 'No description available for this module yet.'}
                    </CardDescription>
                  </div>
                  <Badge className={statusTone}>
                    {module.enabled ? 'Enabled' : 'Disabled'}
                  </Badge>
                </div>
              </CardHeader>
              <CardContent className="space-y-4 px-5 pb-4">
                <div className="text-sm">
                  <div>
                    <div className="text-xs uppercase tracking-[0.14em] text-muted-foreground">Version</div>
                    <div className="mt-1 font-medium">{module.version}</div>
                  </div>
                </div>
              </CardContent>
              <CardFooter className="border-t bg-background px-5 py-4">
                <Button
                  variant={actionVariant}
                  className="w-full"
                  disabled={isProcessing}
                  onClick={() => updateModuleStatus(module.name, !module.enabled)}
                >
                  {isProcessing ? 'Updating...' : actionLabel}
                </Button>
              </CardFooter>
            </Card>
          )
        })}

        {modules.length === 0 ? (
          <Card className="col-span-full border-dashed bg-muted/20 py-0">
            <CardContent className="flex min-h-44 flex-col items-center justify-center gap-3 px-6 py-10 text-center">
              <SearchIcon className="size-5 text-muted-foreground" />
              <div>
                <div className="font-medium">No modules found</div>
                <div className="text-sm text-muted-foreground">Try a different search or filter.</div>
              </div>
            </CardContent>
          </Card>
        ) : null}
      </section>
    </AppLayout>
  )
}
