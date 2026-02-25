// Alpine.js script block
document.addEventListener('alpine:init', () => {
    Alpine.data('expiringItemsAlert', () => ({
        showAlert: false,
        showExpired: false,
        expiring7Days: 0,
        expiring14Days: 0,
        expiring21Days: 0,
        expiredItems: 0,
        init() {
            fetch('get_expiring_items.php')
                .then(response => response.json())
                .then(data => {
                    this.expiring7Days = data.expiring7Days;
                    this.expiring14Days = data.expiring14Days;
                    this.expiring21Days = data.expiring21Days;
                    this.expiredItems = data.expiredItems;
                    this.showAlert = (this.expiring7Days > 0 || this.expiring14Days > 0 || this.expiring21Days > 0);
                    this.showExpired = data.expiredItems > 0;
                })
                .catch(error => {
                    console.error('Error fetching expiring items:', error);
                });
        },
        hasExpiring() {
            return (this.expiring7Days > 0 || this.expiring14Days > 0 || this.expiring21Days > 0);
        },
        redirectToManageItems(filter) {
            try {
                const url = new URL('manage_item.php', window.location.origin);
                if (filter === 'expired') {
                    url.searchParams.set('expirationFilter', 'expired');
                } else if (filter === 'expiring') {
                    url.searchParams.set('expirationFilter', '7');
                }
                window.location.href = url.href;
            } catch (error) {
                console.error('Error constructing URL:', error);
                window.location.href = 'manage_item.php';
            }
        }
    }));
});
// [FIX] Removed Alpine.start() to prevent "already initialized" error