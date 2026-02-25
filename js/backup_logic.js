function backupSystem() {
    return {
        isBackingUp: false,
        progress: 0,
        nextBackupTime: null, // This is just for display (e.g., "11/10/2025, 10:00 AM")

        init() {
            this.loadNextBackupTime();
            
            // Auto-check for backup every 1 minute (60,000ms)
            setInterval(() => {
                // We pass 'false' to the interval check to prevent the "Not Due" popup
                this.confirmBackup(false); 
            }, 60000);
        },

        async getLastBackupTime() {
            let lastBackupTime = localStorage.getItem("lastBackupTime");

            if (lastBackupTime) {
                return parseInt(lastBackupTime);
            } else {
                // Fetch from JSON file if localStorage is cleared
                try {
                    let response = await fetch("backup-time.json" + "?v=" + Date.now()); // Add cache-buster
                    let data = await response.json();
                    return data.lastBackupTime ? parseInt(data.lastBackupTime) : null;
                } catch (error) {
                    return null;
                }
            }
        },

        /**
         * Confirms if a backup is due.
         * @param {boolean} showNotDueMessage - Show "Not Due" tippy if triggered manually.
         */
        async confirmBackup(showNotDueMessage = true) {
            // Get the *numeric timestamp* from localStorage
            let nextBackupTimestamp = Number(localStorage.getItem("nextBackupTime")) || 0;

            // If no next backup is scheduled, or if it's past the scheduled time
            if (!nextBackupTimestamp || Date.now() > nextBackupTimestamp) {
                Swal.fire({
                    title: "Backup Initialization",
                    text: "A scheduled backup is due. Do you want to proceed?",
                    icon: "info",
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Yes, Start Backup",
                    cancelButtonText: "No, Postpone 24h", // Changed text for clarity 
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        this.startBackup();
                    } else if (result.dismiss === Swal.DismissReason.cancel) {
                        // **PROFESSIONAL FIX**: Snooze for 24 hours
                        let oneDay = 24 * 60 * 60 * 1000;
                        let snoozeTime = Date.now() + oneDay;
                        
                        localStorage.setItem("nextBackupTime", snoozeTime.toString());
                        this.nextBackupTime = new Date(snoozeTime).toLocaleString();
                        this.showTippy(`⚠️ Backup postponed. Next reminder in 24 hours.`, "info");
                    }
                });
            } else if (showNotDueMessage) {
                // Only show this if triggered manually (e.g., by a button)
                this.showTippy("✔️ Backup is not yet due!", "success");
            }
        },

        /**
         * Calculates and saves the next backup time based on the last one.
         */
        scheduleNextBackup(lastBackupTime) {
            let oneWeek = 7 * 24 * 60 * 60 * 1000; // 7 days in milliseconds
            let nextBackupTimestamp = lastBackupTime + oneWeek; // Calculate numeric timestamp

            // Persist the next backup timestamp
            localStorage.setItem("nextBackupTime", nextBackupTimestamp.toString());

            // Update the display property
            this.nextBackupTime = new Date(nextBackupTimestamp).toLocaleString();
            this.showTippy(`⏳ Next backup scheduled for ${this.nextBackupTime}`, "info");

            // Also save the *last* backup time to backup-time.json 
            fetch("save-backup-time.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ lastBackupTime: lastBackupTime })
            });
        },

        /**
         * Loads the next backup time from storage or calculates it.
         */
        async loadNextBackupTime() {
            let nextBackupTimestamp = Number(localStorage.getItem("nextBackupTime")) || 0;

            if (!nextBackupTimestamp) {
                // If not in localStorage, calculate it from the server's lastBackupTime
                let lastBackup = await this.getLastBackupTime();
                if (lastBackup) {
                    // This will calculate, save to localStorage, and set this.nextBackupTime
                    this.scheduleNextBackup(lastBackup);
                }
                return;
            }

            // If we have a timestamp, update the display property
            if (nextBackupTimestamp > Date.now()) {
                this.nextBackupTime = new Date(nextBackupTimestamp).toLocaleString();
                this.showTippy(`⏳ Next backup scheduled for ${this.nextBackupTime}`, "info");
            }
        },

        startBackup() {
            this.isBackingUp = true;
            this.progress = 0;

            fetch('backup.php', { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let interval = setInterval(() => {
                            if (this.progress < 100) {
                                this.progress += 20;
                            } else {
                                clearInterval(interval);
                                this.isBackingUp = false;

                                // ✅ SUCCESS TOAST NOTIFICATION
                                Toastify({
                                    text: `✅ Backup Completed! Transactions: ${data.transactions_backed_up} | Items: ${data.items_backed_up}`,
                                    duration: 5000,
                                    gravity: "top",
                                    position: "right",
                                    backgroundColor: "#28a745",
                                    stopOnFocus: true
                                }).showToast();

                                let backupTime = Date.now();
                                localStorage.setItem("lastBackupTime", backupTime.toString()); // Store successful backup time
                                this.scheduleNextBackup(backupTime); // Schedule next backup
                            }
                        }, 500);
                    } else {
                        this.isBackingUp = false;

                        // ❌ ERROR TOAST NOTIFICATION
                        Toastify({
                            text: `❌ Backup Failed: ${data.message}`,
                            duration: 5000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "#dc3545",
                            stopOnFocus: true
                        }).showToast();
                    }
                })
                .catch(() => {
                    this.isBackingUp = false;

                    // ⚠️ WARNING TOAST NOTIFICATION
                    Toastify({
                        text: "⚠️ An unexpected error occurred.",
                        duration: 5000,
                        gravity: "top",
                        position: "right",
                        backgroundColor: "#ffc107",
                        stopOnFocus: true
                    }).showToast();
                });
        },

        showTippy(message, type) {
            let bgColor = {
                success: "#28a745",
                error: "#dc3545",
                info: "#17a2b8"
            }[type] || "#6c757d";

            let targetElement = document.getElementById("backUpStatus");

            if (!targetElement) {
                console.warn("⚠️ Warning: 'backUpStatus' div not found. Tippy.js tooltip cannot be attached.");
                return;
            }

            // Destroy previous tippy instance if it exists, to prevent overlap
            if (targetElement._tippy) {
                targetElement._tippy.destroy();
            }

            tippy(targetElement, {
                content: message,
                placement: "top",
                theme: "custom",
                animation: "fade",
                duration: [300, 300],
                allowHTML: true,
                trigger: "manual",
                onShow(instance) {
                    setTimeout(() => instance.hide(), 10000); // Auto-hide after 10 seconds
                }
            }).show();
        }
    };
}