/**
 * CENTRAL SYNC SYSTEM (Alpine.js Component)
 * Version: Full Bi-Directional Reporting
 */
function syncSystem() {
    return { 
        isOnline: navigator.onLine,
        isSyncing: false,
        statusTitle: 'System Ready',
        statusMessage: 'Waiting for network...',
        progressWidth: 0,
        syncUrl: 'sync_push.php', 
        pullUrl: 'sync_pull.php', 
        retryCount: 0,
        
        initSync() {
            window.addEventListener('online', () => { 
                this.isOnline = true; 
                this.statusTitle = 'Back Online';
                this.triggerSync(); 
            });
            window.addEventListener('offline', () => { 
                this.isOnline = false; 
                this.statusTitle = 'Offline Mode';
                this.isSyncing = false; 
                this.progressWidth = 0;
            });

            if (this.isOnline) setTimeout(() => this.triggerSync(), 2000);
            
            // Auto-sync every 60s
            setInterval(() => { 
                if (this.isOnline && !this.isSyncing) this.triggerSync(); 
            }, 60000);
        },

        triggerSync() {
            if (!navigator.onLine) { this.isOnline = false; return; }
            if (this.isSyncing) return;

            this.isSyncing = true;
            this.statusTitle = 'Syncing...';
            this.statusMessage = 'Checking for updates...';
            this.progressWidth = 20;

            let pushStats = {};

            fetch(this.syncUrl)
                .then(res => res.json())
                .then(data => {
                    this.progressWidth = 60;
                    if(data.status === 'success') {
                        pushStats = data.synced_counts || {};
                        return fetch(this.pullUrl);
                    } else throw new Error(data.msg);
                })
                .then(res => res.json())
                .then(data => {
                    this.progressWidth = 100;
                    if(data.status === 'success') {
                        this.generateReport(pushStats, data.stats);
                        
                        // [NEW] Trigger UI Refresh if deletions occurred
                        if (data.stats.items_deleted > 0) {
                            if (typeof window.viewItems === 'function') window.viewItems(1);
                        }
                    } else throw new Error(data.message);
                })
                .catch(err => this.handleError(err.message));
        },

        // [NEW FUNCTION]: Generates Combined Feedback for Push & Pull
        generateReport(pushData, pullData) {
            let reportParts = [];

            // 1. Format PUSH Stats (Uploads)
            for (const [key, count] of Object.entries(pushData)) {
                if (count > 0) {
                    // Arrow Up (↑) indicates Upload
                    reportParts.push(`↑ ${count} ${this.formatLabel(key, count)}`);
                }
            }
            //new deletion process
            for (const [key, count] of Object.entries(pullData)) {
                if (count > 0 && key !== 'total_processed') {
                    // [NEW] Special Styling for Deletions
                    let icon = key === 'items_deleted' ? '🗑️' : '↓';
                    reportParts.push(`${icon} ${count} ${this.formatLabel(key, count)}`);
                }
            }


            // 2. Format PULL Stats (Downloads)
            for (const [key, count] of Object.entries(pullData)) {
                if (count > 0 && key !== 'total_processed') {
                    // Arrow Down (↓) indicates Download
                    reportParts.push(`↓ ${count} ${this.formatLabel(key, count)}`);
                }
            }

            // 3. Update HUD Display
            if (reportParts.length > 0) {
                this.statusTitle = 'Sync Complete';
                this.statusMessage = reportParts.join(' | '); // Separator
                
                // Keep the success message visible for 8s so admin can read it
                setTimeout(() => { 
                    this.progressWidth = 0; 
                    this.isSyncing = false;
                }, 8000); 
            } else {
                this.statusTitle = 'Up to Date';
                this.statusMessage = 'System is fully synced.';
                setTimeout(() => { 
                    this.progressWidth = 0; 
                    this.isSyncing = false; 
                }, 2500);
            }
        },

        handleError(msg) {
            this.retryCount++;
            this.isSyncing = false;
            this.progressWidth = 0;
            this.statusTitle = 'Sync Paused';
            this.statusMessage = 'Retrying in background...'; 
            console.warn("Sync Error Detail:", msg);
        },

        formatLabel(key, count) {
            // Map technical table names to user-friendly terms
            // Covers keys from both sync_push.php and sync_pull.php
            const map = { 
                // Common / Push Keys
                'transactions': count === 1 ? 'Sale' : 'Sales', 
                'items': count === 1 ? 'Inv. Item' : 'Inv. Items',
                'users': count === 1 ? 'User' : 'Users',
                'suppliers': count === 1 ? 'Supplier' : 'Suppliers',
                'audit_logs': 'Logs',
                
                // Pull Specific Keys
                'items_inserted': count === 1 ? 'New Item' : 'New Items',
                'items_updated': count === 1 ? 'Item Upd.' : 'Items Upd.',
                'categories': count === 1 ? 'Category' : 'Categories',
                'items_deleted': 'Deleted', // [NEW]
            };
            return map[key] || key; 
        }
    }
}