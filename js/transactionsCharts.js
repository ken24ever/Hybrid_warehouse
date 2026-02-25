$(document).ready(function() {

    // Function to format numbers with commas and 2 decimal places
    function formatCurrency(amount) {
        let num = parseFloat(amount);
        if (isNaN(num)) return CURRENCY + "0.00";
        return num.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // Helper function to resolve Branch Context
function getBranchContext() {
        let currentBranch = '';
        if (typeof ACTIVE_BRANCH_CONTEXT !== 'undefined') {
            currentBranch = ACTIVE_BRANCH_CONTEXT;
        } else {
            const urlParams = new URLSearchParams(window.location.search);
            currentBranch = urlParams.get('branch_uuid') || '';
        }
        return currentBranch;
    }

    function fetchAll() {
        let currentBranch = getBranchContext();
        console.log("Fetching stats using Context: " + (currentBranch || "Global")); 

        $.ajax({
            url: 'getAllTransactions.php', 
            type: 'GET',
            dataType: 'json',
            data: {
                branch_code: currentBranch
            },
            success: function(response) {
                $('.AllSales').text(CURRENCY + formatCurrency(response.total_sales));
                $('.monthlySales').text(CURRENCY + formatCurrency(response.current_month_total_sales));
                $('.todaySales').text(CURRENCY + formatCurrency(response.current_day_total_sales));
                $('.stockValue').text(CURRENCY + formatCurrency(response.stock_value));
                $('.grossValue').text(CURRENCY + formatCurrency(response.gross_value));
                $('.netValue').text(CURRENCY + formatCurrency(response.net_value));
            },
            error: function(xhr, status, error) {
                console.error("Stats Fetch Error:", error);
            }
        });
    }

    // Call function immediately on load
    fetchAll();
    

    // Function to fetch transaction data for charts
    function fetchTransactionData() {
        var category = $('#category').val();
        var start_Date = $('#start_Date').val();
        var end_Date = $('#end_Date').val();
        
        // Resolve Branch Context for Charts
        var currentBranch = getBranchContext();

        $.ajax({
            url: 'transaction_data.php',
            type: 'GET',
            dataType: 'json',
            data: {
                category: category,
                start_Date: start_Date,
                end_Date: end_Date,
                branch_code: currentBranch // Pass branch context
            },
            success: function(response) {
                generateCharts(response);
            },
            error: function(xhr, status, error) {
                console.log("Chart Data Error:", error);
            }
        });
    }

    $('#category, #start_Date, #end_Date').change(function() {
        fetchTransactionData();
    });

    // Fetch initial transaction data and generate charts
    fetchTransactionData();


    // Function to generate charts using Highcharts
    function generateCharts(data) {
        var salesData = data.salesData;
        var inventoryData = data.inventoryData;

        // Generate the sales chart
        Highcharts.chart('salesChart', {
            chart: {
                type: 'column'
            },
            title: {
                text: 'Sales Performance'
            },
            xAxis: {
                categories: salesData.categories
            },
            yAxis: {
                title: {
                    text: 'Total Sales'
                }
            },
            series: [{
                name: 'Sales',
                data: salesData.sales
            }],
            plotOptions: {
                column: {
                    cursor: 'pointer',
                    point: {
                        events: {
                            click: function() {
                                var clickedDate = this.category;
                                openModalForDate(clickedDate);
                            }
                        }
                    }
                }
            }
        });

        // Generate the inventory chart
        Highcharts.chart('inventoryChart', {
            chart: {
                type: 'line'
            },
            title: {
                text: 'Inventory Levels'
            },
            xAxis: {
                categories: inventoryData.categories
            },
            yAxis: {
                title: {
                    text: 'Inventory Quantity'
                }
            },
            series: [{
                name: 'Quantity',
                data: inventoryData.quantity
            }],
            plotOptions: {
                line: {
                    cursor: 'pointer',
                    point: {
                        events: {
                            click: function() {
                                var clickedDate = this.category;
                                openModalForDate(clickedDate);
                            }
                        }
                    }
                }
            }
        });
    } // end of generateCharts(data)

    var currentPage = 1; 
    var currentDate; 

    // Function to open the modal for a specific date
    function openModalForDate(date) {
        currentPage = 1;
        currentDate = date;
        fetchTransactionsByDate(date, currentPage);
    }

    // Function to fetch transactions for a specific date and page
function fetchTransactionsByDate(date, page) {
    $('#modalDate').text(date);

    // 1. Resolve Branch Context strictly
    // This ensures we only fetch data for the active branch (or Head Office)
    var currentBranch = '';
    if (typeof ACTIVE_BRANCH_CONTEXT !== 'undefined') {
        currentBranch = ACTIVE_BRANCH_CONTEXT;
    } else {
        const urlParams = new URLSearchParams(window.location.search);
        currentBranch = urlParams.get('branch_uuid') || '';
    }

    console.log("Fetching modal details for Branch: " + (currentBranch || "Global"));

    $.ajax({
        url: 'transaction_data_by_date.php',
        type: 'GET',
        dataType: 'json',
        data: {
            date: date,
            page: page,
            branch_code: currentBranch // <--- CRITICAL FIX: Pass the context
        },
        success: function(response) {
            $('#modalContent').empty();

            if (response.error) {
                    $('#modalContent').html(`<div class="alert alert-danger">${response.error}</div>`);
                    $('#myModal').modal('show');
                    return;
                }

            var transactions = response.transactions;
            if (transactions && transactions.length > 0) {
                var filterForm = $('<form>').addClass('form-inline');
                    var filterLabel = $('<label>').addClass('mr-2').text('Filter By Dates:');
                    var filterSelect = $('<input>').addClass('form-control mr-2').attr('id', 'categoryFilter').attr('type', 'date');;

                    filterForm.append(filterLabel).append(filterSelect);

                    filterSelect.on('change', function() {
                        var category = $(this).val();
                        filterTable(category);
                    });

                    $('#modalContent').append(filterForm);

                var table = $('<table>').addClass('table');
                    var tableHead = $('<thead>').append('<tr><th></th><th>Transaction ID</th><th>Transaction Dates</th><th>Profit/Loss</th><th>Item ID</th><th>Item Name</th><th>Item Description</th><th>Purchase Price</th><th>Sold At</th><th>Quantity in Stock</th><th>Transaction Type</th><th>Quantity</th><th>Total Amount</th></tr>');
                    var tableBody = $('<tbody>');

                    for (var i = 0; i < transactions.length; i++) {
                        var transaction = transactions[i];
                        var row = $('<tr>');
                        row.append('<td><input type="checkbox" class="export-checkbox" data-transaction-id="' + transaction.transaction_id + '"></td>');
                        row.append('<td>' + transaction.transaction_id + '</td>');
                        row.append('<td>' + transaction.transaction_date + '</td>');
                        row.append('<td>' + transaction.profit_loss + '</td>');
                        row.append('<td>' + transaction.item_id + '</td>');
                        row.append('<td>' + transaction.item_name + '</td>');
                        row.append('<td>' + transaction.item_description + '</td>');
                        row.append('<td>' + transaction.purchase_price + '</td>');
                        row.append('<td>' + transaction.sold_at + '</td>');
                        row.append('<td>' + transaction.quantity_in_stock + '</td>');
                        row.append('<td>' + transaction.transaction_type + '</td>');
                        row.append('<td>' + transaction.quantity + '</td>');
                        row.append('<td>' + transaction.total_amount + '</td>');
                        tableBody.append(row);
                    }

                    table.append(tableHead).append(tableBody);
                    $('#modalContent').append(table);

                    
                var selectAllCheckbox = $('<input>').attr('type', 'checkbox').attr('id', 'selectAllCheckbox');
                var selectAllLabel = $('<label>').text('Select All').attr('for', 'selectAllCheckbox');
                selectAllCheckbox.on('change', function() {
                    $('.export-checkbox').prop('checked', $(this).prop('checked'));
                });

                $('#modalContent').prepend(selectAllCheckbox).prepend(selectAllLabel);

                var exportButton = $('<button>').addClass('btn btn-success').text('Export to Excel');
                $('#exportBut').html(exportButton);
                exportButton.on('click', function() {
                    exportSelectedTransactions();
                });

                // Pass current page to pagination update
                updateModalPagination(response.total_pages, response.current_page);
         } else {
                    $('#modalContent').html('<div class="alert alert-info">No transactions found for this date in the current branch context.</div>');
                }

            $('#myModal').modal('show');
        },
        error: function(xhr, status, error) {
            console.log("Error fetching modal data:", error);
            $('#modalContent').html('<p class="text-danger">An error occurred while loading data.</p>');
        }
    });
}
    // Function to update the modal pagination links
    function updateModalPagination(totalPages, currentPage) {
        var paginationContainer = $('#modalPagination');
        paginationContainer.empty();

        var previousButton = $('<button>').addClass('btn btn-primary').text('Previous');
        if (currentPage === 1) {
            previousButton.addClass('disabled');
        } else {
            previousButton.click(function() {
                var page = currentPage - 1;
                fetchTransactionsByDate(currentDate, page);
            });
        }

        var nextButton = $('<button>').addClass('btn btn-primary').text('Next');
        if (currentPage === totalPages) {
            nextButton.addClass('disabled');
        } else {
            nextButton.click(function() {
                var page = currentPage + 1;
                fetchTransactionsByDate(currentDate, page);
            });
        }

        var paginationList = $('<ul>').addClass('pagination justify-content-center');

        var maxVisiblePages = 5;
        var startPage = 1;
        var endPage = totalPages;

        if (totalPages > maxVisiblePages) {
            var middlePage = Math.ceil(maxVisiblePages / 2);
            var leftEllipsis = $('<li>').addClass('page-item disabled').append($('<span>').addClass('page-link').text('...'));
            var rightEllipsis = $('<li>').addClass('page-item disabled').append($('<span>').addClass('page-link').text('...'));

            if (currentPage <= middlePage) {
                endPage = maxVisiblePages - 2;
                nextButton.removeClass('disabled');
            } else if (currentPage > totalPages - middlePage) {
                startPage = totalPages - maxVisiblePages + 3;
                previousButton.removeClass('disabled');
            } else {
                startPage = currentPage - Math.floor(maxVisiblePages / 2) + 2;
                endPage = currentPage + Math.floor(maxVisiblePages / 2) - 2;
                previousButton.removeClass('disabled');
                nextButton.removeClass('disabled');
            }

            if (startPage > 1) {
                paginationList.append(leftEllipsis);
            }

            for (var i = startPage; i <= endPage; i++) {
                var link = $('<a>').attr('href', '#').addClass('page-link').text(i);
                if (i === currentPage) {
                    link.addClass('active');
                }
                link.click(function(e) {
                    e.preventDefault();
                    var page = parseInt($(this).text());
                    fetchTransactionsByDate(currentDate, page);
                });
                var listItem = $('<li>').addClass('page-item').append(link);
                paginationList.append(listItem);
            }

            if (endPage < totalPages) {
                paginationList.append(rightEllipsis);
            }
        } else {
            for (var i = 1; i <= totalPages; i++) {
                var link = $('<a>').attr('href', '#').addClass('page-link').text(i);
                if (i === currentPage) {
                    link.addClass('active');
                }
                link.click(function(e) {
                    e.preventDefault();
                    var page = parseInt($(this).text());
                    fetchTransactionsByDate(currentDate, page);
                });
                var listItem = $('<li>').addClass('page-item').append(link);
                paginationList.append(listItem);
            }
        }

        paginationList.prepend(previousButton);
        paginationList.append(nextButton);
        paginationContainer.append(paginationList);
    }

    function filterTable(category) {
        var tableRows = $('#modalContent table tbody tr');
        if (category === '') {
            tableRows.show();
        } else {
            tableRows.each(function() {
                var transactionType = $(this).find('td:nth-child(3)').text();
                if (transactionType !== category) {
                    $(this).hide();
                } else {
                    $(this).show();
                }
            });
        }
    }

    function exportSelectedTransactions() {
        var selectedTransactionIds = [];
        $('.export-checkbox:checked').each(function() {
            var transactionId = $(this).data('transaction-id');
            selectedTransactionIds.push(transactionId);
        });

        if (selectedTransactionIds.length > 0) {
            $.ajax({
                url: 'export_transactions.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    transactionIds: selectedTransactionIds,
                },
                success: function(response) {
                    if (response.fileUrl) {
                        downloadFile(response.fileUrl);
                    }
                },
                error: function(xhr, status, error) {
                    console.log(error);
                }
            });
        }
    }

    function downloadFile(fileUrl) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', fileUrl, true);
        xhr.responseType = 'blob';

        xhr.onload = function() {
            if (xhr.status === 200) {
                var blob = new Blob([xhr.response], {
                    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                });
                saveAs(blob, 'transactions.xlsx');
            }
        };
        xhr.send();
    }

});