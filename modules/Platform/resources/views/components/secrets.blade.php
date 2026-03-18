@props(['secretableType', 'secretableId'])

<div x-data="secretsManager('{{ $secretableType }}', {{ $secretableId }})" class="mt-6">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-medium text-gray-900">Secrets</h3>
        <button @click="openCreateModal" class="btn btn-primary btn-sm">
            Add Secret
        </button>
    </div>

    <div class="bg-white shadow overflow-hidden sm:rounded-md">
        <ul role="list" class="divide-y divide-gray-200">
            <template x-for="secret in secrets" :key="secret.id">
                <li class="px-4 py-4 sm:px-6 flex items-center justify-between">
                    <div class="flex flex-col">
                        <span class="text-sm font-medium text-indigo-600 truncate" x-text="secret.key"></span>
                        <span class="text-xs text-gray-500" x-text="secret.type"></span>
                    </div>
                    <div class="flex space-x-2">
                        <button @click="viewSecret(secret.id)" class="text-gray-400 hover:text-gray-500">
                            <span class="sr-only">View</span>
                            <!-- Eye Icon -->
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                        <button @click="editSecret(secret)" class="text-gray-400 hover:text-gray-500">
                            <!-- Pencil Icon -->
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>
                        <button @click="deleteSecret(secret.id)" class="text-red-400 hover:text-red-500">
                            <!-- Trash Icon -->
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </li>
            </template>
            <template x-if="secrets.length === 0">
                <li class="px-4 py-8 text-center text-gray-500 text-sm">No secrets found.</li>
            </template>
        </ul>
    </div>

    <!-- Modal -->
    <div x-show="isModalOpen" class="fixed inset-0 z-10 overflow-y-auto" style="display: none;">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" x-text="modalTitle"></h3>
                    <div class="mt-2 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Key</label>
                            <input type="text" x-model="form.key" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Username/Email</label>
                            <input type="text" x-model="form.username" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Optional">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Type</label>
                            <select x-model="form.type" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                <option value="password">Password</option>
                                <option value="api_key">API Key</option>
                                <option value="certificate">Certificate</option>
                                <option value="ssh_key">SSH Key</option>
                                <option value="json">JSON</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Value</label>
                            <textarea x-model="form.value" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"></textarea>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" @click="saveSecret" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Save
                    </button>
                    <button type="button" @click="closeModal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Secret Modal -->
     <div x-show="isViewModalOpen" class="fixed inset-0 z-10 overflow-y-auto" style="display: none;">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
             <div class="fixed inset-0 transition-opacity" @click="isViewModalOpen = false" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
             <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                 <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                     <h3 class="text-lg font-medium">Secret Details</h3>
                     <div class="mt-4 space-y-3">
                         <div x-show="viewedSecretUsername">
                             <label class="block text-sm font-medium text-gray-500">Username</label>
                             <div class="bg-gray-100 p-2 rounded break-all font-mono text-sm" x-text="viewedSecretUsername"></div>
                         </div>
                         <div>
                             <label class="block text-sm font-medium text-gray-500">Value</label>
                             <div class="bg-gray-100 p-2 rounded break-all font-mono text-sm" x-text="viewedSecretValue"></div>
                         </div>
                     </div>
                 </div>
                 <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" @click="isViewModalOpen = false" class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:w-auto sm:text-sm">
                        Close
                    </button>
                 </div>
             </div>
        </div>
     </div>

</div>

<script>
    function secretsManager(type, id) {
        const csrfToken = '{{ csrf_token() }}';
        const secretsIndexUrl = `{{ route('platform.secrets.index') }}?secretable_type=${type}&secretable_id=${id}`;
        const secretResourceUrl = `{{ route('platform.secrets.show', '') }}`;

        return {
            secretableType: type,
            secretableId: id,
            secrets: [],
            isModalOpen: false,
            isViewModalOpen: false,
            modalTitle: 'Add Secret',
            viewedSecretValue: '',
            viewedSecretUsername: '',
            form: {
                id: null,
                key: '',
                username: '',
                type: 'password',
                value: '',
                secretable_type: type,
                secretable_id: id
            },
            init() {
                this.loadSecrets();
            },
            async requestJson(url, options = {}) {
                const { body, headers = {}, ...requestOptions } = options;

                const response = await fetch(url, {
                    headers: {
                        Accept: 'application/json',
                        ...(body ? { 'Content-Type': 'application/json' } : {}),
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                        ...headers,
                    },
                    ...requestOptions,
                    ...(body ? { body: JSON.stringify(body) } : {}),
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || 'Failed to process secret request.');
                }

                return data;
            },
            async loadSecrets() {
                const data = await this.requestJson(secretsIndexUrl);
                this.secrets = data.secrets;
            },
            openCreateModal() {
                this.resetForm();
                this.modalTitle = 'Add Secret';
                this.isModalOpen = true;
            },
            editSecret(secret) {
                this.form = { ...secret, value: '', username: secret.username || '' }; // Pre-fill username, leave value blank
                this.modalTitle = 'Edit Secret';
                this.isModalOpen = true;
            },
            async viewSecret(id) {
                const data = await this.requestJson(`${secretResourceUrl}/${id}`);
                this.viewedSecretValue = data.secret.decrypted_value;
                this.viewedSecretUsername = data.secret.username || '';
                this.isViewModalOpen = true;
            },
            async deleteSecret(id) {
                if (!confirm('Are you sure?')) return;

                await this.requestJson(`${secretResourceUrl}/${id}`, {
                    method: 'DELETE',
                });

                await this.loadSecrets();
            },
            async saveSecret() {
                const url = this.form.id
                    ? `${secretResourceUrl}/${this.form.id}`
                    : `{{ route('platform.secrets.store') }}`;

                const method = this.form.id ? 'PUT' : 'POST';

                await this.requestJson(url, {
                    method,
                    body: this.form,
                });

                this.isModalOpen = false;
                await this.loadSecrets();
                this.resetForm();
            },
            closeModal() {
                this.isModalOpen = false;
                this.resetForm();
            },
            resetForm() {
                this.form = {
                    id: null,
                    key: '',
                    username: '',
                    type: 'password',
                    value: '',
                    secretable_type: this.secretableType,
                    secretable_id: this.secretableId
                };
            }
        }
    }
</script>
