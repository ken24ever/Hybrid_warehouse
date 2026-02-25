$(document).ready(function() {
    var purchaseInvoiceModal = document.getElementById("purchaseInvoiceModal");
    var showPurchaseInvoiceModalBtn = document.getElementById("showPurchaseInvoiceModal");
    var closePurchaseInvoiceModalBtn = document.getElementById("closePurchaseInvoiceModal");
    var tableId = '#purchaseInvoiceTable';

    // Open Modal
    showPurchaseInvoiceModalBtn.onclick = function() {
        purchaseInvoiceModal.style.display = "block";
        loadPurchaseInvoiceSummary(); // Always load summary first
    };

    // Close Modal
    closePurchaseInvoiceModalBtn.onclick = function() {
        closeModal();
    };

    // Click Outside
    window.onclick = function(event) {
        if (event.target === purchaseInvoiceModal) {
            closeModal();
        }
    };

    function closeModal() {
        purchaseInvoiceModal.style.display = "none";
        // Clean up table
        if ($.fn.DataTable.isDataTable(tableId)) {
            $(tableId).DataTable().destroy();
        }
        // Remove any injected "Back" buttons
        $('#invoiceBackButton').remove();
        $(tableId).empty(); // Clear header/body for fresh load next time
    }

    // --- VIEW 1: SUMMARY (Grouped Invoices) ---
    function loadPurchaseInvoiceSummary() {
        // 1. Destroy existing table
        if ($.fn.DataTable.isDataTable(tableId)) {
            $(tableId).DataTable().destroy();
        }
        $(tableId).empty(); // Clear old headers
        $('#invoiceBackButton').remove(); // Hide back button if exists

        // 2. Initialize DataTable for Summary
        var purchaseInvoiceTable = $(tableId).DataTable({
            ajax: {
                url: 'fetch_purchase_invoices.php?action=summary',
                dataSrc: 'data'
            },
            columns: [
                { title: "Invoice #", data: 'invoice_number' },
                { title: "Date Purchased", data: 'date_purchased' },
                { title: "Supplier", data: 'supplier_info' },
                { title: "Total Items", data: 'item_count' },
                { 
                    title: "Invoice Total", 
                    data: 'invoice_total',
                    className: 'font-weight-bold text-success', // <- ADDED CLASS HERE
                    render: function(data, type, row) {
                        // Assuming global CURRENCY variable exists from superAdmin.php
                        return (typeof CURRENCY !== 'undefined' ? CURRENCY : '$') + parseFloat(data).toLocaleString(); 
                    }
                },
                {
                    title: "Action",
                    data: null,
                    className: "text-center",
                    render: function(data, type, row) {
                        return `<button class="btn btn-sm btn-primary view-details-btn" data-invoice="${row.invoice_number}">
                                    <i class="ti-eye"></i> View Details
                                </button>`;
                    }
                }
            ],
            // UI Options
            paging: true,
            lengthChange: true,
            searching: true,
            ordering: true,
            info: true,
            autoWidth: false,
            responsive: true,
            pageLength: 10,
            language: { search: '<i class="fas fa-search"></i>', searchPlaceholder: 'Search Invoices...' },
            initComplete: function() {
                $(this).find('thead th').addClass('bg-gray-100 text-gray-700');
            }
        });

        // 3. Attach Event Listener for "View Details" (Using delegation)
        $(tableId + ' tbody').off('click', '.view-details-btn').on('click', '.view-details-btn', function() {
            var invoiceNum = $(this).data('invoice');
            loadPurchaseInvoiceDetails(invoiceNum);
        });
    }

    // --- VIEW 2: DETAILS (Items inside an Invoice) ---
    function loadPurchaseInvoiceDetails(invoiceNumber) {
        // 1. Destroy existing table
        if ($.fn.DataTable.isDataTable(tableId)) {
            $(tableId).DataTable().destroy();
        }
        $(tableId).empty(); // Clear old headers

        // 2. Add "Back" Button above table if not present
        if ($('#invoiceBackButton').length === 0) {
            $('<button id="invoiceBackButton" class="btn btn-secondary mb-3"><i class="ti-arrow-left"></i> Back to Invoices</button>')
                .insertBefore(tableId)
                .on('click', function() {
                    loadPurchaseInvoiceSummary();
                });
        }

        // 3. Initialize DataTable for Details
        $(tableId).DataTable({
            ajax: {
                url: 'fetch_purchase_invoices.php',
                type: 'GET',
                data: {
                    action: 'details',
                    invoice_number: invoiceNumber
                },
                dataSrc: 'data'
            },
            columns: [
                { title: "Invoice #", data: 'invoice_number' }, // Still useful to see
                { title: "Item Name", data: 'item_name' },
                { title: "Description", data: 'item_description' },
                { title: "Qty In Stock", data: 'quantity_in_stock' },
                { 
                    title: "Unit Cost", 
                    data: 'purchase_price',
                    render: function(data) { return parseFloat(data).toFixed(2); }
                },
                { 
                    title: "Total Line Cost", 
                    data: 'total_line_cost', // Calculated in PHP
                    render: function(data) { 
                        return (typeof CURRENCY !== 'undefined' ? CURRENCY : '$') + parseFloat(data).toLocaleString(); 
                    }
                }
            ],
            ordering: true,
            searching: true, // Allows searching specific items within the invoice
            paging: true,
            info: true,
            language: { search: '<i class="fas fa-search"></i>', searchPlaceholder: 'Search Items...' },
            initComplete: function() {
                $(this).find('thead th').addClass('bg-gray-100 text-gray-700');
            }
        });
    }

    // Existing custom filtering logic (Date/Invoice/Supplier)
    // You may want to hide these filters when in "Details" mode or adapt them.
    $('#dateFilter, #invoiceFilter, #supplierFilter').on('input', function() {
        var table = $(tableId).DataTable();
        table.draw();
    });
});