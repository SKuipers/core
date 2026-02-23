/**
 * TokenInput - Alpine.js + HTMX autocomplete with token creation
 * Replacement for jQuery tokenInput plugin
 * Uses Alpine.js for reactivity, HTMX for AJAX, and Tailwind CSS for styling
 * 
 * @version 2.0
 * @since jQuery Removal Phase 4
 */

class TokenInput {
    constructor(input, options) {
        if (typeof input === 'string') {
            input = document.querySelector(input);
        }
        
        if (!input) {
            console.error('TokenInput: Input element not found');
            return;
        }
        
        this.input = input;
        this.options = Object.assign({
            theme: 'facebook',
            hintText: 'Start typing...',
            noResultsText: 'No results',
            searchingText: 'Searching...',
            allowFreeTagging: false,
            preventDuplicates: true,
            tokenLimit: null,
            minChars: 1,
            resultsLimit: null,
            enableHTML: true,
            prePopulate: [],
            resultsFormatter: null,
            tokenFormatter: null,
            onAdd: null,
            onDelete: null,
            onResult: null,
            excludeCurrent: false
        }, options);
        
        this.dataSource = null;
        this.isAjax = false;
        
        // Determine data source type
        if (typeof this.options.dataSource === 'string') {
            this.dataSource = this.options.dataSource;
            this.isAjax = true;
        } else if (Array.isArray(this.options.dataSource)) {
            this.dataSource = this.options.dataSource;
            this.isAjax = false;
        }
        
        this.init();
    }
    
    init() {
        // Hide original input
        this.input.style.display = 'none';
        
        // Create Alpine.js component wrapper
        this.container = document.createElement('div');
        this.container.setAttribute('x-data', 'tokenInput()');
        this.container.setAttribute('x-init', 'init()');
        this.container.classList.add('relative');
        this.container.classList.add('w-full');
        
        // Build the component HTML with Alpine.js directives and Tailwind classes
        this.container.innerHTML = `
            
                <!-- Token list container -->
                <ul 
                    @click="focusInput()"
                    :class="{'ring-2 ring-blue-500': focused, 'opacity-50 cursor-not-allowed': disabled}"
                    class="list-none m-0 flex flex-wrap items-center gap-2 p-2 bg-white border  rounded-lg min-h-[42px] cursor-text transition-all"
                >
                    <!-- Tokens -->
                    <template x-for="(token, index) in tokens" :key="token.id">
                        <li class="flex items-center gap-1 m-0 px-3 py-1 text-sm bg-blue-100 text-blue-800 rounded-full border border-blue-200">
                            <span x-html="formatToken(token)"></span>
                            <button 
                                @click.stop="removeToken(index)"
                                :disabled="disabled"
                                type="button"
                                class="ml-1 text-blue-600 hover:text-blue-800 font-bold focus:outline-none disabled:cursor-not-allowed"
                            >×</button>
                        </li>
                    </template>
                    
                    <!-- Search input -->
                    <li class="flex-1 min-w-[100px]">
                        <input 
                            x-ref="searchInput"
                            x-model="query"
                            @input.debounce.300ms="search()"
                            @keydown="handleKeydown($event)"
                            @focus="focused = true; search()"
                            @blur="focused = false; setTimeout(() => showDropdown = false, 200)"
                            :placeholder="tokens.length === 0 ? hintText : ''"
                            :disabled="disabled || (tokenLimit && tokens.length >= tokenLimit)"
                            type="text"
                            autocomplete="off"
                            class="w-full border-0 focus:ring-0 focus:outline-none text-sm p-0 disabled:bg-transparent disabled:cursor-not-allowed"
                        />
                    </li>
                </ul>
                
                <!-- Dropdown results -->
                <div 
                    x-show="showDropdown"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute z-[999] w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-auto"
                >
                    <!-- Searching state -->
                    <div x-show="searching" class="p-3 text-sm text-gray-500 italic">
                        <span x-text="searchingText"></span>
                    </div>
                    
                    <!-- No results -->
                    <div x-show="!searching && results.length === 0 && !allowFreeTagging" class="p-3 text-sm text-gray-500">
                        <span x-text="noResultsText"></span>
                    </div>
                    
                    <!-- Free tagging option -->
                    <div x-show="!searching && results.length === 0 && allowFreeTagging && query.trim()" class="p-3">
                        <button 
                            @click="selectResult({id: query.trim(), name: query.trim()})"
                            type="button"
                            class="w-full text-left px-3 py-2 text-sm hover:bg-blue-50 rounded"
                            x-text="query.trim()"
                        ></button>
                    </div>
                    
                    <!-- Results list -->
                    <ul x-show="!searching && results.length > 0" class="list-none m-0 py-1">
                        <template x-for="(result, index) in results" :key="result.id">
                            <li>
                                <button
                                    @click="selectResult(result)"
                                    @mouseenter="selectedIndex = index"
                                    :class="{'bg-blue-500 text-white': selectedIndex === index, 'hover:bg-gray-100': selectedIndex !== index}"
                                    type="button"
                                    class="w-full text-left px-4 py-2 text-sm transition-colors"
                                    x-html="formatResult(result)"
                                ></button>
                            </li>
                        </template>
                    </ul>
                </div>
            
        `;
        
        // Insert into DOM
        this.input.parentElement.insertBefore(this.container, this.input.nextSibling);
        
        // Initialize Alpine.js component data
        this.initAlpineData();
        
        // Store reference on input element
        this.input.tokenInputInstance = this;
    }
    
    initAlpineData() {
        const self = this;
        
        // Define Alpine.js component data and methods
        window.tokenInput = function() {
            return {
                // Reactive state
                tokens: [],
                query: '',
                results: [],
                selectedIndex: -1,
                showDropdown: false,
                searching: false,
                focused: false,
                disabled: false,
                resultsCache: {},
                
                // Options from constructor
                hintText: self.options.hintText,
                noResultsText: self.options.noResultsText,
                searchingText: self.options.searchingText,
                allowFreeTagging: self.options.allowFreeTagging,
                preventDuplicates: self.options.preventDuplicates,
                tokenLimit: self.options.tokenLimit,
                minChars: self.options.minChars,
                resultsLimit: self.options.resultsLimit,
                
                // Initialize
                init() {
                    // Pre-populate tokens
                    if (self.options.prePopulate && self.options.prePopulate.length > 0) {
                        this.tokens = [...self.options.prePopulate];
                        this.updateHiddenInput();
                    }
                },
                
                // Focus the search input
                focusInput() {
                    if (!this.disabled) {
                        this.$refs.searchInput.focus();
                    }
                },
                
                // Search for results
                async search() {
                    const query = this.query.trim();
                    
                    if (query.length < this.minChars) {
                        this.showDropdown = false;
                        return;
                    }
                    
                    if (this.tokenLimit && this.tokens.length >= this.tokenLimit) {
                        this.showDropdown = false;
                        return;
                    }
                    
                    if (self.isAjax) {
                        await this.searchAjax(query);
                    } else {
                        this.searchLocal(query);
                    }
                },
                
                // AJAX search using fetch (HTMX could be used for more complex scenarios)
                async searchAjax(query) {
                    // Check cache
                    if (this.resultsCache[query]) {
                        this.results = this.resultsCache[query];
                        this.showDropdown = true;
                        return;
                    }
                    
                    this.searching = true;
                    this.showDropdown = true;
                    
                    try {
                        const url = self.dataSource + (self.dataSource.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(query);
                        
                        // Add exclude current tokens if enabled
                        let fetchUrl = url;
                        if (self.options.excludeCurrent && this.tokens.length > 0) {
                            const tokenIds = this.tokens.map(t => t.id).join(',');
                            fetchUrl += '&excludeIds=' + encodeURIComponent(tokenIds);
                        }
                        
                        const response = await fetch(fetchUrl);
                        let results = await response.json();
                        
                        // Apply onResult callback if provided
                        if (self.options.onResult) {
                            results = self.options.onResult(results);
                        }
                        
                        // Cache results
                        this.resultsCache[query] = results;
                        this.results = this.filterResults(results);
                        this.selectedIndex = -1;
                    } catch (error) {
                        console.error('TokenInput: AJAX error', error);
                        this.results = [];
                    } finally {
                        this.searching = false;
                    }
                },
                
                // Local search
                searchLocal(query) {
                    const queryLower = query.toLowerCase();
                    let results = self.dataSource.filter(item => {
                        const name = (item.name || '').toLowerCase();
                        return name.includes(queryLower);
                    });
                    
                    // Apply results limit
                    if (this.resultsLimit) {
                        results = results.slice(0, this.resultsLimit);
                    }
                    
                    // Apply onResult callback if provided
                    if (self.options.onResult) {
                        results = self.options.onResult(results);
                    }
                    
                    this.results = this.filterResults(results);
                    this.showDropdown = true;
                    this.selectedIndex = -1;
                },
                
                // Filter out duplicates
                filterResults(results) {
                    if (this.preventDuplicates) {
                        const tokenIds = this.tokens.map(t => String(t.id));
                        return results.filter(item => !tokenIds.includes(String(item.id)));
                    }
                    return results;
                },
                
                // Select a result
                selectResult(item) {
                    this.addToken(item);
                    this.query = '';
                    this.results = [];
                    this.showDropdown = false;
                    this.selectedIndex = -1;
                    this.$refs.searchInput.focus();
                },
                
                // Add a token
                addToken(item) {
                    // Check token limit
                    if (this.tokenLimit && this.tokens.length >= this.tokenLimit) {
                        return;
                    }
                    
                    // Check for duplicates
                    if (this.preventDuplicates) {
                        const exists = this.tokens.some(t => String(t.id) === String(item.id));
                        if (exists) {
                            return;
                        }
                    }
                    
                    this.tokens.push(item);
                    this.updateHiddenInput();
                    
                    // Trigger change event
                    self.input.dispatchEvent(new Event('change', { bubbles: true }));
                    
                    // Call onAdd callback
                    if (self.options.onAdd) {
                        self.options.onAdd(item);
                    }
                },
                
                // Remove a token
                removeToken(index) {
                    const item = this.tokens[index];
                    this.tokens.splice(index, 1);
                    this.updateHiddenInput();
                    
                    // Trigger change event
                    self.input.dispatchEvent(new Event('change', { bubbles: true }));
                    
                    // Call onDelete callback
                    if (self.options.onDelete) {
                        self.options.onDelete(item);
                    }
                },
                
                // Update hidden input value
                updateHiddenInput() {
                    const ids = this.tokens.map(t => t.id).join(',');
                    self.input.value = ids;
                },
                
                // Format result for display
                formatResult(item) {
                    if (self.options.resultsFormatter) {
                        return self.options.resultsFormatter(item);
                    }
                    return this.escapeHtml(item.name || item.id);
                },
                
                // Format token for display
                formatToken(item) {
                    if (self.options.tokenFormatter) {
                        return self.options.tokenFormatter(item);
                    }
                    if (self.options.enableHTML) {
                        return item.name || item.id;
                    }
                    return this.escapeHtml(item.name || item.id);
                },
                
                // Handle keyboard navigation
                handleKeydown(event) {
                    switch (event.key) {
                        case 'ArrowDown':
                            event.preventDefault();
                            if (this.results.length > 0) {
                                this.selectedIndex = Math.min(this.selectedIndex + 1, this.results.length - 1);
                            }
                            break;
                            
                        case 'ArrowUp':
                            event.preventDefault();
                            if (this.results.length > 0) {
                                this.selectedIndex = Math.max(this.selectedIndex - 1, 0);
                            }
                            break;
                            
                        case 'Enter':
                            event.preventDefault();
                            if (this.selectedIndex >= 0 && this.results[this.selectedIndex]) {
                                this.selectResult(this.results[this.selectedIndex]);
                            } else if (this.allowFreeTagging && this.query.trim()) {
                                const query = this.query.trim();
                                this.selectResult({ id: query, name: query });
                            }
                            break;
                            
                        case 'Backspace':
                            if (this.query === '' && this.tokens.length > 0) {
                                event.preventDefault();
                                this.removeToken(this.tokens.length - 1);
                            }
                            break;
                            
                        case 'Escape':
                            this.showDropdown = false;
                            this.selectedIndex = -1;
                            break;
                    }
                },
                
                // Escape HTML
                escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }
            };
        };
    }
    
    // Public API methods
    clear() {
        const alpineData = Alpine.$data(this.container);
        if (alpineData) {
            alpineData.tokens = [];
            alpineData.updateHiddenInput();
            this.input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
    
    add(item) {
        const alpineData = Alpine.$data(this.container);
        if (alpineData) {
            alpineData.addToken(item);
        }
    }
    
    remove(item) {
        const alpineData = Alpine.$data(this.container);
        if (alpineData) {
            const index = alpineData.tokens.findIndex(t => t.id === item.id);
            if (index >= 0) {
                alpineData.removeToken(index);
            }
        }
    }
    
    get() {
        const alpineData = Alpine.$data(this.container);
        return alpineData ? [...alpineData.tokens] : [];
    }
    
    toggleDisabled(disable) {
        const alpineData = Alpine.$data(this.container);
        if (alpineData) {
            alpineData.disabled = disable;
        }
    }
    
    destroy() {
        this.container.remove();
        this.input.style.display = '';
        delete this.input.tokenInputInstance;
    }
}

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TokenInput;
}
