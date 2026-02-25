// view_item.js - FULLY MERGED & REFACTORED

// ==========================================
// 1. HELPER FUNCTIONS & DEFINITIONS 
// ==========================================

// Global cache for items
let allItems = []; 

// Global cache for Item History
let currentItemHistory = [];
let currentHistoryUsers = [];
let currentHistoryFilters = {
    startDate: '',
    endDate: '',
    user: 'all',
    actionType: 'all'
};
const HISTORY_ITEMS_PER_PAGE = 10;

// Helper: Parse date string to Date object
function parseDate(dateStr) {
  return new Date(dateStr); 
}

// Helper: Calculate days left until expiration
function daysUntilExpiration(expirationDate) {
  const today = new Date();
  today.setHours(0, 0, 0, 0); 
  const expDate = parseDate(expirationDate);
  expDate.setHours(0, 0, 0, 0); 
  return Math.floor((expDate - today) / (1000 * 60 * 60 * 24));
}

// Helper: Sanitize Input for HTML display
function sanitizeInput(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Add "Reporting" button HTML
function addReportingButton(item) {
    return `<button class="btn btn-warning btn-sm reporting-item btn-reporting" data-item-id="${item.item_id}" data-item-name="${item.item_name}">Reporting</button>`;
}

// Render a single item row
function renderItemRow(item) {
    let rowClass = '';
    let statusBadgeClass = '';
    let statusText = item.status; 

    if (typeof ENABLE_LOW_STOCK_ALERT !== 'undefined' && ENABLE_LOW_STOCK_ALERT) {
        if (item.quantity_in_stock == 0) {
            rowClass = 'table-danger'; statusBadgeClass = 'out-of-stock'; statusText = 'Out of Stock';
        } else if (item.quantity_in_stock < (typeof LOW_STOCK_THRESHOLD !== 'undefined' ? LOW_STOCK_THRESHOLD : 10)) {
            rowClass = 'table-warning'; statusBadgeClass = 'low-stock'; statusText = 'Low Stock';
        } else {
            statusBadgeClass = 'in-stock'; statusText = 'In Stock';
        }
    } else {
        if (item.quantity_in_stock == 0) {
            rowClass = 'table-danger'; statusBadgeClass = 'out-of-stock'; statusText = 'Out of Stock';
        } else {
            statusBadgeClass = 'in-stock'; statusText = 'In Stock';
        }
    }

    if (item.expiration_date) {
        const daysLeft = daysUntilExpiration(item.expiration_date);
        if (daysLeft < 0) { rowClass = 'table-danger'; } 
    }

    return `
        <tr class="${rowClass}">
            <td><input type="checkbox" class="delete-checkbox" value="${item.item_id}"></td>
            <td>${item.item_id}</td>
            <td>${item.item_unique_no}</td>
            <td>${item.item_name}</td>
            <td>${item.item_description}</td>
            <td>${item.purchase_price}</td>
            <td>${item.wholesale}</td>
            <td>${item.retail}</td>
            <td>${item.quantity_in_stock}</td>
            <td><span class="status-badge ${statusBadgeClass}">${statusText}</span></td>
            <td>${item.category}</td>
            <td>${item.expiration_date || ''}</td>
            <td>
                <button class="btn btn-primary btn-sm mr-2 edit-item btn-edit" data-item-id="${item.item_id}">Edit</button>
                ${addReportingButton(item)}
            </td>
        </tr>
    `;
}

// Helper: Handle Online/Offline Context & Dynamic Branch
function resolveBranchContext() {
    // 1. Check for Dynamic Context Variable (Injected by PHP)
    if (typeof ACTIVE_BRANCH_CONTEXT !== 'undefined' && ACTIVE_BRANCH_CONTEXT) {
        
        // 2. Offline Check:
        if (!navigator.onLine) {
            
            // [CRITICAL FIX] Determine if we are viewing "Home" or "Remote"
            // We expect USER_SESSION_BRANCH to be defined. If missing, we default to assuming local.
            let homeBranch = (typeof USER_SESSION_BRANCH !== 'undefined') ? USER_SESSION_BRANCH : ACTIVE_BRANCH_CONTEXT;
            
            // CHECK: Is the user trying to view a DIFFERENT branch while offline?
            if (ACTIVE_BRANCH_CONTEXT !== homeBranch) {
                
                // SCENARIO: Offline + Viewing Remote Branch -> SHOW WARNING
                if (!sessionStorage.getItem('offline_remote_alert_shown')) {
                    Toastify({
                        text: "⚠️ Offline: Cannot connect to remote branch (" + ACTIVE_BRANCH_CONTEXT + ")",
                        duration: 6000,
                        gravity: "top", position: "right",
                        style: { background: "#dc3545", color: "#fff" } // Red for Error
                    }).showToast();
                    sessionStorage.setItem('offline_remote_alert_shown', 'true');
                }
                
            } else {
                // SCENARIO: Offline + Viewing Local Branch -> SILENT (No Toast)
                // As requested: Do not show error when viewing own branch
                console.log("System Offline: Viewing Local Cache (Silent Mode)");
            }

        } else {
            // Online: Clear flags so toasts can show again if connection drops
            sessionStorage.removeItem('offline_remote_alert_shown');
        }

        // Returns the active branch code defined in manage_item.php
    return (typeof ACTIVE_BRANCH_CONTEXT !== 'undefined') ? ACTIVE_BRANCH_CONTEXT : '';
    }

    // 3. Fallback
    console.warn("Branch Context Missing! Check manage_item.php injection.");
    return ''; 
}

// Filter items logic
function displayFilteredItems(daysFilter, categoryFilter, statusFilter = 'all') {
    const tableBody = $("#itemsTableBody");
    tableBody.empty();

    let filteredItems = allItems;

    // 1. Apply Expiration Filter (Fixed NaN error for 'expired')
    if (daysFilter !== 'all') {
        filteredItems = filteredItems.filter(item => {
            if (!item.expiration_date) return false;
            const daysLeft = daysUntilExpiration(item.expiration_date);
            
            if (daysFilter === 'expired') {
                return daysLeft < 0; // Show already expired items
            } else {
                const maxDays = parseInt(daysFilter);
                return daysLeft <= maxDays && daysLeft >= 0;
            }
        });
    }

    // 2. Apply Category Filter
    if (categoryFilter !== 'all') {
        filteredItems = filteredItems.filter(item => item.category === categoryFilter);
    }

// 3. Apply Status Filter (Mapped to HTML Values)
    if (statusFilter !== 'all') {
        filteredItems = filteredItems.filter(item => {
            const qty = parseInt(item.quantity_in_stock) || 0;
            // Use global threshold if defined, otherwise default to 10
            const threshold = (typeof LOW_STOCK_THRESHOLD !== 'undefined') ? LOW_STOCK_THRESHOLD : 10; 
            
            // [FIX] Accept both semantic names and color-code names (red/amber) from HTML
            if (statusFilter === 'out' || statusFilter === 'red') {
                return qty <= 0;
            } else if (statusFilter === 'low' || statusFilter === 'amber') {
                return qty > 0 && qty < threshold; // Warning zone
            } else if (statusFilter === 'good' || statusFilter === 'green') {
                return qty >= threshold; // Safe zone
            }
            return true;
        });
    }

    // 4. Render the Filtered Items (Fixed ReferenceError)
    // Iterate through the array and build the HTML using the existing renderItemRow function
    let rowsHtml = '';
    filteredItems.forEach(item => {
        rowsHtml += renderItemRow(item);
    });
    
    // Append all rows to the table body at once (better for performance)
    tableBody.append(rowsHtml);
}

// ==========================================
// 2. GLOBAL FUNCTION DEFINITIONS
// ==========================================

window.viewItems = function(page) { 
    // [FIX]: Get the dynamic branch code
    let targetBranch = resolveBranchContext();

    if (!targetBranch) {
        console.error("Cannot view items: Branch context is undefined.");
        return;
    }

    // [ADDITION] Show Loading State for better UX during remote fetch
    $('#itemsTableBody').html('<tr><td colspan="13" class="text-center p-4"><div class="spinner-border text-primary"></div> Loading inventory...</td></tr>');

    $.ajax({
        url: 'view_items.php', 
        type: 'GET',
        dataType: 'json',
        data: { 
            page: page, 
            branch_code: targetBranch // Pass the dynamic branch
        },
        success: function (response) {
            allItems = response.items; // Store items globally
            
            // Clear table before appending
            $('#itemsTableBody').empty();

         
        // [PROFESSIONAL FIX] Pass all required filter arguments 
            // to prevent the array from returning empty.
            displayFilteredItems(
                $('#expirationFilter').val() || 'all', 
                $('#categoryFilter').val() || 'all', 
                $('#filterByStatus').val() || 'all' // [FIX] ID Updated
            );
            processExpirationAlerts(allItems);

            if($('#totalItemsCount').length) {
                // Dynamic Label based on response
                let sourceLabel = response.source ? ` <span class="badge badge-secondary" style="font-size:0.7em">${response.source}</span>` : '';
                $('#totalItemsCount').html(`
                    <b>Total Items (${response.branch}):</b> ${response.total_items} | 
                    <b>Total Quantity:</b> ${response.total_quantity}
                    ${sourceLabel}
                `);
            }

            let lowStockCount = allItems.filter(i => i.quantity_in_stock < (typeof LOW_STOCK_THRESHOLD !== 'undefined' ? LOW_STOCK_THRESHOLD : 10) && i.quantity_in_stock > 0).length;
            let outOfStockCount = allItems.filter(i => i.quantity_in_stock == 0).length;
            displayStockAlerts(lowStockCount, outOfStockCount);

            updatePagination(response.total_pages, page);
        },
        error: function (xhr, status, error) {
            console.error("View Items Error:", error);
            $('#itemsTableBody').html('<tr><td colspan="13" class="text-center text-danger p-4">Error loading items. Check connection.</td></tr>');
            Toastify({ text: "Error loading items. Check connection.", style: { background: "red" } }).showToast();
        }
    }); 
};

window.loadCategories = function() {
    let targetBranch = resolveBranchContext();
    if (!targetBranch) return;

    console.log("Loading Categories for:", targetBranch);

    $.ajax({
        url: 'get_categories.php', 
        type: 'GET',
        dataType: 'json',
        data: { branch_code: targetBranch },
        success: function (categories) {
            $('#categoryFilter').empty().append('<option value="">All Categories</option>');
            $('#categorySelect').empty(); 
            
            if (categories.length > 0) {
                categories.forEach(category => {
                    let opt = `<option value="${category.category_name}">${category.category_name}</option>`;
                    $('#categoryFilter').append(opt);
                    $('#categorySelect').append(opt);
                    if($('#category_Select').is('select')) $('#category_Select').append(opt);
                }); 
            }
        },
        error: function (xhr) {
            console.error('Failed to load categories:', xhr.responseText);
        }
    });
};

// ========================================== 
// 3. EDIT ITEM HANDLER (Requested Fix)
// ==========================================
$(document).on('click', '.edit-item', function () {
    var itemId = $(this).data('item-id');
    var currentBranch = resolveBranchContext(); // CRITICAL: Get Context

    // Make an AJAX request to get the item details
    $.ajax({
      url: 'get_item_details.php',
      type: 'GET',
      dataType: 'json',
      data: {
        itemId: itemId,
        branch_code: currentBranch // CRITICAL: Pass Context to PHP
      },
      success: function (response) {
        if(response.error) {
            Toastify({ text: response.error, style: { background: "red" } }).showToast();
            return;
        }

        // Sanitize all inputs before assigning 
        $('#itembarcode').val(sanitizeInput(response.item_unique_no));//new entry
        $('#itemID').val(sanitizeInput(response.item_id));
        $('#itemName').val(sanitizeInput(response.item_name));
        $('#itemDescription').val(sanitizeInput(response.item_description));
        $('#itemPrice').val(sanitizeInput(response.purchase_price));
        $('#wholesale_price').val(sanitizeInput(response.wholesale));
        $('#retail_price').val(sanitizeInput(response.retail));
        $('#itemQuantity').val(sanitizeInput(response.quantity_in_stock));
        $('#itemStatus').val(sanitizeInput(response.status));
        
    $('#category_Sel').html("<strong style='color: red !important;'>"+sanitizeInput(response.category)+"</strong>");
    
        $('#expiration_date').val(sanitizeInput(response.expiration_date));
        $('#invoiceNumber').val(sanitizeInput(response.invoice_number));
        $('#supplierName').val(sanitizeInput(response.supplier_info));
        $('#purchaseDate').val(sanitizeInput(response.date_purchased));

        // --- HISTORY LOADING CODE ---
        if (typeof loadItemHistory === 'function') {
            loadItemHistory(itemId);
        }
        
        $('#itemModalTabs a[href="#editDetailsTab"]').tab('show');

        // Show the edit form modal
        $('#editItemModal').modal('show');
      },
      error: function (xhr, status, error) {
        console.log("Error loading item: ", error);
        $('#noHistoryMessage').show().text('Error loading item details.');
      }
    });
});




// ==========================================
// 4. INITIALIZATION
// ==========================================

    loadCategories();
    viewItems(1);
    
    // Auto-refresh on network change
    window.addEventListener('online',  () => { viewItems(1); loadCategories(); });
    window.addEventListener('offline', () => { viewItems(1); loadCategories(); });



// Bind filters (Corrected ID to match manage_item.php)
$('#expirationFilter, #categoryFilter, #filterByStatus').on('change', function () { 
    const selectedDays = $('#expirationFilter').val() || 'all';
    const selectedCategory = $('#categoryFilter').val() || 'all';
    const selectedStatus = $('#filterByStatus').val() || 'all'; // [FIX] ID Updated
    displayFilteredItems(selectedDays, selectedCategory, selectedStatus);
});
// Select All Checkbox
$(document).on('change', '.delete-checkbox:first', function () {
    $('.delete-checkbox').prop('checked', $(this).prop('checked'));
});

// Excel Export
$(document).on('click', '#exportToExcel', function() {
    const table = document.getElementById('itemsTableBody');
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.table_to_sheet(table);
    XLSX.utils.book_append_sheet(wb, ws, 'Items');
    XLSX.writeFile(wb, 'items.xlsx'); 
});

// Display Stock Alerts Logic
function displayStockAlerts(lowStockCount, outOfStockCount) {
    if (lowStockCount > 0) {
        $("#lowStockAlert").removeClass("d-none").find("#lowStockCount").text(lowStockCount);
    } else {
        $("#lowStockAlert").addClass("d-none");
    }
    if (outOfStockCount > 0) {
        $("#outOfStockAlert").removeClass("d-none").find("#outOfStockCount").text(outOfStockCount);
    } else {
        $("#outOfStockAlert").addClass("d-none");
    }
}

// Search Filter
$(document).on('input', '#searchInput', function () {
  let searchValue = $(this).val().trim().toLowerCase(); 
  $('#itemsTableBody tr').each(function () {
      let uniqueNo = $(this).find('td:nth-child(3)').text().trim().toLowerCase(); 
      let name = $(this).find('td:nth-child(4)').text().trim().toLowerCase(); 
      $(this).toggle(uniqueNo.includes(searchValue) || name.includes(searchValue));
  });
});

// Pagination Logic
function updatePagination(totalPages, currentPage) {
    const paginationControls = $('#paginationControls');
    paginationControls.empty();
    if (totalPages <= 1) return;

    paginationControls.append(`<li class="page-item ${currentPage === 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${currentPage - 1}">Previous</a></li>`);
    for (let i = 1; i <= totalPages; i++) {
        paginationControls.append(`<li class="page-item ${currentPage === i ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`);
    }
    paginationControls.append(`<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${currentPage + 1}">Next</a></li>`);

    paginationControls.find('.page-link').on('click', function(e) {
        e.preventDefault();
        const newPage = parseInt($(this).data('page'));
        if (newPage > 0 && newPage <= totalPages) viewItems(newPage);
    });
}



// --- BATCH DELETE ---
$(document).on('click', '#deleteSelectedBtn', function () {
    let selectedItems = [];
    
    // Collect checked items
    $('.delete-checkbox:not(:first):checked').each(function () {
        selectedItems.push($(this).val());
    });

    // 1. Validation: No Items
    if (selectedItems.length === 0) {
        Swal.fire({ 
            icon: 'warning', 
            title: 'No Items Selected', 
            text: 'Please select at least one item to delete.' 
        });
        return;
    }

    // 2. Validation: Offline Check (Network Connectivity)
    // [STRICT] Prevent any deletion attempts if the system is offline
    if (!navigator.onLine) {
        Swal.fire({ 
            icon: 'error', 
            title: 'Offline Mode', 
            html: 'You cannot delete items while offline.<br>This ensures data consistency between Local and Cloud.',
            confirmButtonColor: '#d33'
        });
        return;
    }

    // 3. Context Resolution (Dynamic) 
    let currentBranch = resolveBranchContext(); 
    
    // Alert user if deleting from a specific context (Local or Remote)
    let branchLabel = (currentBranch) 
                      ? `<br><span class="badge badge-info">Active Context: ${currentBranch}</span>` 
                      : '';

    Swal.fire({
        title: 'Are you sure?', 
        html: `This action cannot be undone! ${branchLabel}`, 
        icon: 'warning',
        showCancelButton: true, 
        confirmButtonColor: '#d33', 
        cancelButtonColor: '#3085d6', 
        confirmButtonText: 'Yes, delete!',
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'delete_item.php',
                type: 'POST',
                dataType: 'json',
                data: { 
                    itemIds: selectedItems, 
                    branch_code: currentBranch // Pass the resolved dynamic context
                },
                beforeSend: function () {
                    Swal.fire({ 
                        title: 'Deleting...', 
                        text: 'Verifying Cloud Connectivity...',
                        allowOutsideClick: false, 
                        didOpen: () => { Swal.showLoading(); } 
                    });
                },
          
                success: function (response) {
                    if (response.success) {
                        Swal.fire({ 
                            icon: 'success', 
                            title: 'Deleted!', 
                            text: response.message 
                        }).then(() => {
                            if (typeof window.viewItems === 'function') {
                                window.viewItems(1);
                            } else {
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire({ 
                            icon: 'error', 
                            title: 'Deletion Failed', 
                            text: response.message 
                        });
                    }
                },
                // [PROFESSIONAL FIX] Improved Error Handler
                error: function (xhr, status, error) {
                    let errorTitle = 'System Error';
                    let errorMsg = 'An unexpected error occurred.';

                    // Check if it's actually a network disconnect
                    if (xhr.status === 0) {
                        errorTitle = 'Connection Error';
                        errorMsg = 'Failed to connect to server. Ensure you are online.';
                    } 
                    // Check for Authorization/Permission Rejections
                    else if (xhr.status === 401 || xhr.status === 403) {
                        errorTitle = 'Access Denied';
                        errorMsg = 'You do not have permission to perform this action.';
                    } 
                    // Catch HTTP 500 or JSON Parse Errors (PHP Crashes)
                    else {
                        errorTitle = `Server Error (${xhr.status})`;
                        try {
                            let res = JSON.parse(xhr.responseText);
                            errorMsg = res.message || 'Invalid response from server.';
                        } catch (e) {
                            errorMsg = 'The backend script crashed. Check the browser console for details.';
                            console.error("PHP Fatal Error Dump:", xhr.responseText);
                        }
                    }

                    Swal.fire({ 
                        icon: 'error', 
                        title: errorTitle, 
                        text: errorMsg 
                    });
                },
            });
        }
    });
});




// ==========================================
// SUBMIT EDIT FORM (Cleaned - Backend Verification Only) 
// ==========================================
$(document).on('submit', '#editItemForm', function(event) {
    event.preventDefault();
    
    // 1. Get Context & Data
    var currentBranch = resolveBranchContext();
    var formData = $(this).serialize();
    formData += '&branch_code=' + encodeURIComponent(currentBranch);

    // 2. Safety Check: Context Existence
    if (!currentBranch) {
        Toastify({ 
            text: "Error: Could not determine Branch Context. Please refresh.", 
            style: { background: "red" } 
        }).showToast();
        return;
    }

    // 3. SHOW LOADER & SUBMIT DIRECTLY
    // We removed the "get_branches.php" check. The server (update_item.php) 
    // will now perform the Real-Time Heartbeat check.
    $('#loader').fadeIn();
    performItemUpdate(formData);
});

// Helper function to execute the actual update after verification passes
function performItemUpdate(formData) {
    $.ajax({
        url: 'update_item.php',
        type: 'POST',
        dataType: 'json',
        data: formData,
        success: function(response) {
            $('#loader').fadeOut();
            
            if (response.success) {
                $('#editItemModal').modal('hide');
                
                // Refresh View
                if (typeof window.viewItems === 'function') window.viewItems(1);

                // Toast Report
                let msg = response.message;
                if (response.changes && response.changes.length > 0) {
                    msg = "<strong>" + response.message + "</strong><br/>" + 
                          response.changes.map(c => "• " + c).join("<br/>");
                }

                Toastify({ 
                    text: msg, 
                    escapeMarkup: false, 
                    duration: 5000, 
                    style: { background: "linear-gradient(to right, #00b09b, #96c93d)" } 
                }).showToast();

            } else {
                Toastify({ 
                    text: response.message, 
                    style: { background: "linear-gradient(to right, #ff5f6d, #ffc371)" } 
                }).showToast();
            }
        },
        error: function(xhr) {
            $('#loader').fadeOut();
            Toastify({ 
                text: "Server Error during update.", 
                style: { background: "red" } 
            }).showToast();
        }
    });
}

// --- REPORTING MODAL ---
$(document).on('click', '.reporting-item', function () {
    var itemName = $(this).data('item-name');
    var itemId = $(this).data('item-id');

    $('#reportingModal').data('item-id', itemId).modal('show');
    $('.modal-title').html("Item Reporting for <strong style='font-size:18px;'> " + itemName + "</strong>");
    $("#specificDate").val('');
    $("#specificMonth").val('');
    $('#reportTotalAmount, #reportProfit, #reportQuantitySold, #reportQuantityStock').text("");
    
    // Initial Chart
    Highcharts.chart('reportChart', {
        title: { text: 'Sales Comparison' }, xAxis: { categories: ['Prev Month', 'Curr Month'] }, yAxis: { title: { text: 'Amount & Quantity' } },
        series: [{ name: 'Quantity Sold', data: [] }, { name: 'Total Amount', data: [] }]
    });
});

// --- GENERATE REPORT ---
$(document).on('click','#generateReport',function () {
    var itemId = $('#reportingModal').data('item-id');
    var date = $('#specificDate').val();
    var month = $('#specificMonth').val();

    $.ajax({
        url: 'generate_report.php',
        type: 'POST',
        dataType: 'json',
        data: { itemId: itemId, date: date, month: month, branch_code: resolveBranchContext() },
        success: function (response) {
            if (response.success) {
                $('#reportTotalAmount').text(response.totalAmount);
                $('#reportProfit').text(response.profit);
                $('#reportQuantitySold').text(response.quantitySold);
                $('#reportQuantityStock').text(response.quantityStock);

                Highcharts.chart('reportChart', {
                    title: { text: 'Sales Comparison' }, xAxis: { categories: ['Prev Month', 'Curr Month'] }, yAxis: { title: { text: 'Values' } },
                    series: [
                        { name: 'Quantity Sold', data: response.chartData.quantity },
                        { name: 'Total Amount', data: response.chartData.amount }
                    ]
                });
            } else {
                alert(response.message);
            }
        },
        error: function(xhr) { console.log(xhr.responseText); }
    });
});

// ==========================================
// 5. HISTORY TAB LOGIC (Updated & Polished)
// ==========================================

function loadItemHistory(itemId) {
    const loader = $('#historyLoader');
    const noHistoryMsg = $('#noHistoryMessage');
    const tableContainer = $('#historyTableContainer');
    const paginationControls = $('#historyPaginationControls');

    loader.show(); 
    noHistoryMsg.hide(); 
    tableContainer.hide(); 
    paginationControls.hide();
    
    $('#itemHistoryTableBody').empty();
    resetHistoryFilters();

    $.ajax({
        url: 'get_item_history.php',
        type: 'GET',
        dataType: 'json',
        data: { itemId: itemId, branch_code: resolveBranchContext() }, 
        success: function(response) {
            loader.hide();
            
            if (response.error) {
                noHistoryMsg.show().text(response.error);
                return;
            }
            
            // [FIX]: Changed response.allHistory to response.history based on your actual JSON
            if (response.history && response.history.length > 0) {
                currentItemHistory = response.history;
                currentHistoryUsers = response.users;
                
                populateHistoryUserFilter(currentHistoryUsers);
                displayHistoryPage(1);
                
                tableContainer.show(); 
                paginationControls.show();
            } else {
                noHistoryMsg.show().text('No history found for this item.'); 
                currentItemHistory = [];
            }
        },
        error: function(xhr) {
            loader.hide();
            noHistoryMsg.show().text('Error loading history.');
            console.error(xhr.responseText);
        }
    });
}

function populateHistoryUserFilter(users) {
    const userFilter = $('#historyUserFilter');
    userFilter.empty().append('<option value="all" selected>All Users</option>');
    users.forEach(user => { 
        userFilter.append(`<option value="${sanitizeInput(user)}">${sanitizeInput(user)}</option>`); 
    });
}

function resetHistoryFilters() {
    $('#historyStartDate, #historyEndDate').val('');
    $('#historyUserFilter, #historyActionFilter').val('all');
    currentHistoryFilters = { startDate: '', endDate: '', user: 'all', actionType: 'all' };
}

function getFilteredHistory() {
    const { startDate, endDate, user, actionType } = currentHistoryFilters;
    const start = startDate ? new Date(startDate) : null; if(start) start.setHours(0,0,0,0);
    const end = endDate ? new Date(endDate) : null; if(end) end.setHours(23,59,59,999);

    return currentItemHistory.filter(event => {
        // Replace - with / for better cross-browser date parsing
        let eventDate = new Date(event.timestamp.replace(/-/g, '/'));
        
        if (start && eventDate < start) return false;
        if (end && eventDate > end) return false;
        if (user !== 'all' && event.username !== user) return false;
        if (actionType !== 'all' && event.action_type !== actionType) return false;
        return true;
    });
}

function displayHistoryPage(page, filteredData = null) {
    const tableBody = $('#itemHistoryTableBody');
    const noHistoryMsg = $('#noHistoryMessage');
    const paginationControls = $('#historyPaginationControls');
    
    tableBody.empty(); 
    noHistoryMsg.hide();

    const historySource = filteredData !== null ? filteredData : getFilteredHistory();

    if (historySource.length === 0) {
        noHistoryMsg.show().text('No records match filters.');
        paginationControls.hide();
        return;
    }

    const totalItems = historySource.length;
    const totalPages = Math.ceil(totalItems / HISTORY_ITEMS_PER_PAGE);
    page = Math.max(1, Math.min(page, totalPages));

    const startIndex = (page - 1) * HISTORY_ITEMS_PER_PAGE;
    const pageData = historySource.slice(startIndex, startIndex + HISTORY_ITEMS_PER_PAGE);

    pageData.forEach(event => {
        // 1. Format Date nicely
        const dateObj = new Date(event.timestamp.replace(/-/g, '/'));
        const dateStr = dateObj.toLocaleDateString() + ' ' + dateObj.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        // 2. Dynamic Badge Styling
        let badgeClass = 'badge-secondary';
        let icon = '<i class="fas fa-cog"></i>';

        if (event.action_type === 'sale') {
            badgeClass = 'badge-success'; 
            icon = '<i class="fas fa-shopping-cart"></i>';
        } else if (event.action_type === 'adjustment') {
            badgeClass = 'badge-warning'; 
            icon = '<i class="fas fa-edit"></i>';
        } else if (event.action_type === 'stock_add') {
            badgeClass = 'badge-info';
            icon = '<i class="fas fa-plus"></i>';
        }

        // 3. Highlight numerical changes (100 -> 200)
        let safeDetails = sanitizeInput(event.details);
        let formattedDetails = safeDetails.replace(
            /(\d+(\.\d{1,2})?) -&gt; (\d+(\.\d{1,2})?)/g, 
            '<span class="text-danger">$1</span> <i class="fas fa-arrow-right text-muted small"></i> <span class="text-success font-weight-bold">$3</span>'
        );
        // Also handle standard "->" if not encoded yet
        formattedDetails = formattedDetails.replace(
            /(\d+(\.\d{1,2})?) -> (\d+(\.\d{1,2})?)/g, 
            '<span class="text-danger">$1</span> <i class="fas fa-arrow-right text-muted small"></i> <span class="text-success font-weight-bold">$3</span>'
        );

        let row = `<tr>
            <td style="white-space:nowrap;">${dateStr}</td>
            <td>${sanitizeInput(event.username)}</td>
            <td><span class="badge ${badgeClass}">${icon} ${sanitizeInput(event.action_type).toUpperCase()}</span></td>
            <td style="font-size: 0.9rem;">${formattedDetails}</td>
        </tr>`;
        
        tableBody.append(row);
    });

    updateHistoryPagination(totalPages, page);
    paginationControls.show();
}

function updateHistoryPagination(totalPages, currentPage) {
    const pagination = $('#historyPaginationControls');
    pagination.empty();
    
    if (totalPages <= 1) return;

    // Previous
    pagination.append(`<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="displayHistoryPage(${currentPage - 1}); return false;">&laquo;</a>
    </li>`);

    // Page Numbers
    for (let i = 1; i <= totalPages; i++) {
        if(i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            pagination.append(`<li class="page-item ${currentPage === i ? 'active' : ''}">
                <a class="page-link" href="#" onclick="displayHistoryPage(${i}); return false;">${i}</a>
            </li>`);
        } else if (i === currentPage - 2 || i === currentPage + 2) {
            pagination.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
        }
    }

    // Next
    pagination.append(`<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="displayHistoryPage(${currentPage + 1}); return false;">&raquo;</a>
    </li>`);
}

// History Filters
$(document).on('click', '#applyHistoryFilterBtn', function() {
    currentHistoryFilters.startDate = $('#historyStartDate').val();
    currentHistoryFilters.endDate = $('#historyEndDate').val();
    currentHistoryFilters.user = $('#historyUserFilter').val();
    currentHistoryFilters.actionType = $('#historyActionFilter').val();
    displayHistoryPage(1);
});

$(document).on('click', '#resetHistoryFilterBtn', function() {
    resetHistoryFilters();
    displayHistoryPage(1);
});


// Alert Logic
function processExpirationAlerts(items) {
    const today = new Date(); today.setHours(0, 0, 0, 0);
    let toastCounts = { week: 0, month: 0, threeMonths: 0 };
    let expiredItemsCount = 0;

    items.forEach(item => {
        if (!item.expiration_date) return;
        const expDate = new Date(item.expiration_date.replace(/-/g, '/')); expDate.setHours(0, 0, 0, 0);
        if (isNaN(expDate.getTime())) return; 
        const daysLeft = Math.floor((expDate - today) / (1000 * 60 * 60 * 24));

        if (daysLeft < 0) expiredItemsCount++;
        else if (daysLeft <= 7) toastCounts.week++;
        else if (daysLeft <= 30) toastCounts.month++;
        else if (daysLeft <= 90) toastCounts.threeMonths++;
    });

    showExpirationToasts(toastCounts);
    $("#expiredItemsAlert").toggleClass("d-none", expiredItemsCount === 0).find("#expiredItemsCount").text(expiredItemsCount);
    $("#expiringSoonAlert").toggleClass("d-none", toastCounts.week === 0).find("#expiringSoonCount").text(toastCounts.week);
}

function showExpirationToasts(counts) {
    const toasts = [];
    if (counts.threeMonths > 0) toasts.push({ icon: 'info', title: `<strong>${counts.threeMonths}</strong> item(s) expire in 2-3 months.`, background: '#e6f7ff', iconColor: '#17a2b8' });
    if (counts.month > 0) toasts.push({ icon: 'warning', title: `<strong>${counts.month}</strong> item(s) expire within 1 month!`, background: '#fff3cd', iconColor: '#ffc107' });
    if (counts.week > 0) toasts.push({ icon: 'error', title: `<strong>${counts.week}</strong> item(s) expire within 1 week!`, background: '#f8d7da', iconColor: '#dc3545' });

    if (toasts.length === 0) return;
    let i = 0;
    function showNextToast() {
        if (i >= toasts.length) return;
        const currentToast = toasts[i];
        Swal.fire({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 5000, timerProgressBar: true,
            html: currentToast.title, icon: currentToast.icon, iconColor: currentToast.iconColor, background: currentToast.background,
            didOpen: (toast) => { toast.addEventListener('mouseenter', Swal.stopTimer); toast.addEventListener('mouseleave', Swal.resumeTimer); },
            willClose: () => { i++; setTimeout(showNextToast, 250); }
        });
    }
    showNextToast();
}