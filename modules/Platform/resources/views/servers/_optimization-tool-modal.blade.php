{{-- Server Optimization Tool Modal --}}
<div class="modal fade" id="optimizationToolModal" tabindex="-1" aria-labelledby="optimizationToolModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" x-data="optimizationTool()" x-init="init()">
            <div class="modal-header">
                <h5 class="modal-title" id="optimizationToolModalLabel">
                    <i class="ri-speed-up-line me-1"></i> Server Optimization
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {{-- Loading State --}}
                <template x-if="loading">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted">Fetching server configuration...</p>
                    </div>
                </template>

                {{-- Error State --}}
                <template x-if="error && !loading">
                    <div class="alert alert-danger d-flex align-items-start">
                        <i class="ri-error-warning-line me-2 mt-1"></i>
                        <div>
                            <strong>Error</strong>
                            <p class="mb-0 mt-1" x-text="error"></p>
                        </div>
                    </div>
                </template>

                {{-- Data Loaded --}}
                <template x-if="!loading && !error && data">
                    <div>
                        <template x-for="category in data.categories" :key="category.id">
                            <div>
                                {{-- Category Header --}}
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="d-flex align-items-center">
                                        <i :class="category.icon + ' fs-4 me-2 text-primary'"></i>
                                        <div>
                                            <h6 class="mb-0" x-text="category.label"></h6>
                                            <small class="text-muted">
                                                Version: <span x-text="category.pg_version"></span>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <span class="badge bg-info-subtle text-info">
                                            <i class="ri-cpu-line me-1"></i><span x-text="category.hardware.cpu_cores"></span> cores
                                        </span>
                                        <span class="badge bg-info-subtle text-info">
                                            <i class="ri-ram-line me-1"></i><span x-text="formatRam(category.hardware.ram_mb)"></span>
                                        </span>
                                        <span class="badge bg-info-subtle text-info" x-show="category.hardware.storage_type !== 'unknown'">
                                            <i class="ri-hard-drive-3-line me-1"></i><span x-text="category.hardware.storage_type.toUpperCase()"></span>
                                        </span>
                                    </div>
                                </div>

                                {{-- Summary Badges --}}
                                <div class="d-flex gap-2 mb-3">
                                    <span class="badge bg-success-subtle text-success">
                                        <i class="ri-check-line me-1"></i><span x-text="countByStatus(category.settings, 'ok')"></span> Optimal
                                    </span>
                                    <span class="badge bg-warning-subtle text-warning">
                                        <i class="ri-arrow-up-down-line me-1"></i><span x-text="countByStatus(category.settings, 'needs_tuning')"></span> Needs Tuning
                                    </span>
                                    <span class="badge bg-secondary-subtle text-secondary" x-show="countByStatus(category.settings, 'unknown') > 0">
                                        <i class="ri-question-line me-1"></i><span x-text="countByStatus(category.settings, 'unknown')"></span> Unknown
                                    </span>
                                </div>

                                {{-- Filter Tabs --}}
                                <ul class="nav nav-tabs nav-sm mb-3">
                                    <li class="nav-item">
                                        <a class="nav-link" :class="{'active': filter === 'all'}" href="#" @click.prevent="filter = 'all'">
                                            All
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" :class="{'active': filter === 'needs_tuning'}" href="#" @click.prevent="filter = 'needs_tuning'">
                                            Needs Tuning
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" :class="{'active': filter === 'ok'}" href="#" @click.prevent="filter = 'ok'">
                                            Optimal
                                        </a>
                                    </li>
                                </ul>

                                {{-- Settings Table --}}
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-muted small fw-semibold" style="width: 24px;"></th>
                                                <th class="text-muted small fw-semibold">Setting</th>
                                                <th class="text-muted small fw-semibold text-end">Current</th>
                                                <th class="text-muted small fw-semibold text-end">Recommended</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="setting in filteredSettings(category.settings)" :key="setting.name">
                                                <tr>
                                                    <td>
                                                        <i x-show="setting.status === 'ok'"
                                                           class="ri-checkbox-circle-fill text-success"></i>
                                                        <i x-show="setting.status === 'needs_tuning'"
                                                           class="ri-arrow-up-down-fill text-warning"></i>
                                                        <i x-show="setting.status === 'unknown'"
                                                           class="ri-question-fill text-secondary"></i>
                                                    </td>
                                                    <td>
                                                        <div class="fw-semibold small font-monospace" x-text="setting.name"></div>
                                                        <div class="text-muted small" x-text="setting.description" style="max-width: 400px;"></div>
                                                    </td>
                                                    <td class="text-end">
                                                        <code class="small" :class="{
                                                            'text-success': setting.status === 'ok',
                                                            'text-warning': setting.status === 'needs_tuning',
                                                            'text-secondary': setting.status === 'unknown'
                                                        }" x-text="setting.current"></code>
                                                    </td>
                                                    <td class="text-end">
                                                        <code class="small text-primary" x-text="setting.recommended"></code>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </template>

                        {{-- Help Note --}}
                        <div class="alert alert-light border mt-3 mb-0 small">
                            <i class="ri-information-line me-1"></i>
                            Recommendations are based on <a href="https://pgtune.leopard.in.ua/" target="_blank" class="text-decoration-none">PGTune</a> formulas
                            and your server's hardware. Click <strong>Apply Optimizations</strong> to apply all recommended changes.
                        </div>

                        {{-- Apply Result --}}
                        <template x-if="applyResult">
                            <div class="alert mt-3 mb-0 small" :class="{
                                'alert-success': applyResult.status === 'success',
                                'alert-warning': applyResult.status === 'partial',
                                'alert-danger': applyResult.status === 'error'
                            }">
                                <div class="d-flex align-items-start">
                                    <i class="me-2 mt-1" :class="{
                                        'ri-checkbox-circle-line': applyResult.status === 'success',
                                        'ri-error-warning-line': applyResult.status !== 'success'
                                    }"></i>
                                    <div>
                                        <strong x-text="applyResult.status === 'success' ? 'Success' : (applyResult.status === 'partial' ? 'Partial Success' : 'Error')"></strong>
                                        <p class="mb-0 mt-1" x-text="applyResult.message"></p>
                                        <template x-if="applyResult.restart_required && !applyResult.restarted">
                                            <p class="mb-0 mt-1 text-warning fw-semibold">
                                                <i class="ri-restart-line me-1"></i>PostgreSQL restart required for some settings to take effect.
                                            </p>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
            <div class="modal-footer" x-show="!loading && !error && data">
                <button type="button" class="btn btn-sm btn-outline-secondary" @click="fetchData()" :disabled="applying">
                    <i class="ri-refresh-line me-1"></i>Refresh
                </button>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                <template x-if="needsTuningCount() > 0 && !applyResult">
                    <button type="button" class="btn btn-sm btn-primary" @click="confirmApply()" :disabled="applying">
                        <template x-if="!applying">
                            <span><i class="ri-tools-line me-1"></i>Apply Optimizations (<span x-text="needsTuningCount()"></span>)</span>
                        </template>
                        <template x-if="applying">
                            <span><span class="spinner-border spinner-border-sm me-1" role="status"></span>Applying...</span>
                        </template>
                    </button>
                </template>
                <template x-if="applyResult && applyResult.status === 'success'">
                    <button type="button" class="btn btn-sm btn-success" disabled>
                        <i class="ri-checkbox-circle-line me-1"></i>Applied
                    </button>
                </template>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function optimizationTool() {
        return {
            loading: false,
            error: null,
            data: null,
            filter: 'all',
            applying: false,
            applyResult: null,

            init() {
                // Load data when modal is shown
                const modal = document.getElementById('optimizationToolModal');
                modal.addEventListener('shown.bs.modal', () => {
                    if (!this.data) {
                        this.fetchData();
                    }
                });
            },

            async fetchData() {
                this.loading = true;
                this.error = null;
                this.applyResult = null;

                try {
                    const response = await fetch('{{ route("platform.servers.optimization-tool", $server->id) }}', {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    const result = await response.json();

                    if (!response.ok || result.status === 'error') {
                        this.error = result.message || 'Failed to load optimization data.';
                        return;
                    }

                    this.data = result.data;
                } catch (e) {
                    this.error = 'Network error. Please try again.';
                } finally {
                    this.loading = false;
                }
            },

            filteredSettings(settings) {
                if (this.filter === 'all') return settings;
                return settings.filter(s => s.status === this.filter);
            },

            countByStatus(settings, status) {
                return settings.filter(s => s.status === status).length;
            },

            needsTuningCount() {
                if (!this.data || !this.data.categories) return 0;
                return this.data.categories.reduce((count, cat) => {
                    return count + cat.settings.filter(s => s.status === 'needs_tuning').length;
                }, 0);
            },

            getTuningSettings() {
                if (!this.data || !this.data.categories) return {};
                let settings = {};
                this.data.categories.forEach(cat => {
                    cat.settings.forEach(s => {
                        if (s.status === 'needs_tuning') {
                            settings[s.name] = s.recommended;
                        }
                    });
                });
                return settings;
            },

            confirmApply() {
                const count = this.needsTuningCount();
                const settings = this.getTuningSettings();
                const settingNames = Object.keys(settings).join(', ');

                const restartSettings = ['max_connections', 'shared_buffers', 'huge_pages', 'max_worker_processes'];
                const needsRestart = Object.keys(settings).some(s => restartSettings.includes(s));
                let message = `Apply ${count} optimization(s)?\n\nSettings: ${settingNames}`;
                if (needsRestart) {
                    message += '\n\n⚠️ Some settings require a PostgreSQL restart to take effect.';
                }

                if (confirm(message)) {
                    this.applySettings(settings);
                }
            },

            async applySettings(settings) {
                this.applying = true;
                this.applyResult = null;

                try {
                    const response = await fetch('{{ route("platform.servers.apply-optimization", $server->id) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                        body: JSON.stringify({ settings }),
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        this.applyResult = {
                            status: 'error',
                            message: result.message || 'Failed to apply optimizations.',
                            restart_required: false,
                            restarted: false,
                        };
                        return;
                    }

                    this.applyResult = {
                        status: result.status,
                        message: result.message,
                        restart_required: result.data?.restart_required || false,
                        restarted: result.data?.restarted || false,
                    };

                    // Refresh data to show updated current values
                    if (result.status === 'success' || result.status === 'partial') {
                        setTimeout(() => this.fetchData(), 1500);
                    }
                } catch (e) {
                    this.applyResult = {
                        status: 'error',
                        message: 'Network error. Please try again.',
                        restart_required: false,
                        restarted: false,
                    };
                } finally {
                    this.applying = false;
                }
            },

            formatRam(mb) {
                if (mb >= 1024) {
                    return (mb / 1024).toFixed(1).replace(/\.0$/, '') + ' GB';
                }
                return mb + ' MB';
            }
        };
    }
</script>
@endpush
