// transactions_users.js
// VERSION: DYNAMIC CONTEXT AWARENESS 

$(document).ready(function() {
    
    // Initial Load
    countAllUsers();

    function countAllUsers() {
        // 1. Resolve Target Branch Dynamically
        // Priority: URL Param > Global PHP Context > Empty (Backend decides)
        const urlParams = new URLSearchParams(window.location.search); 
        let targetBranch = urlParams.get('branch_uuid');

        if (!targetBranch && typeof ACTIVE_BRANCH_CONTEXT !== 'undefined') {
            targetBranch = ACTIVE_BRANCH_CONTEXT;
        }

        // Default to empty string if nothing found (PHP will handle session fallback)
        targetBranch = targetBranch || '';

        $.ajax({
            url: 'countAllUsers.php',
            type: 'GET',
            dataType: 'json',
            data: { branch_code: targetBranch },
            success: function(response) { 
                // 1. Update the Count
                $('.totalUsers').text(response.total_users);

                // 2. CHECK FOR FALLBACK (Offline Warning)
                if (response.is_fallback === true) {
                    if (!window.hasShownFallbackWarning) { 
                        Swal.fire({
                            icon: 'warning',
                            title: 'System Offline',
                            text: `Could not reach Remote Branch. Displaying Local Data for ${response.viewing_branch} instead.`,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 20000
                        });
                        window.hasShownFallbackWarning = true; 
                    }
                    
                    // Visual Indicator
                    $('.totalUsers').addClass('text-warning').attr('title', 'Offline Data');
                } else {
                    $('.totalUsers').removeClass('text-warning').removeAttr('title');
                }
            },
            error: function(xhr, status, error) {
                console.error("Error fetching user count:", error);
                $('.totalUsers').text("0"); 
            }
        });
    }




// ... (Keep the rest of your fetchTransactions logic below) ...
//call function
setInterval(countAllUsers, 2000);

  // Function to fetch transactions with pagination
    function fetchTransactions(page) {
        var transactionType = $('#transactionType').val();
        var transactionUser = $('#transactionUser').val(); // Capture current selection
        var startDate = $('#startDate').val();
        var endDate = $('#endDate').val();

        // Resolve Branch Context
        var currentBranch = '';
        if (typeof ACTIVE_BRANCH_CONTEXT !== 'undefined') {
            currentBranch = ACTIVE_BRANCH_CONTEXT;
        } else {
            const urlParams = new URLSearchParams(window.location.search);
            currentBranch = urlParams.get('branch_uuid') || '';
        }

        console.log("Fetching User Transactions for Branch: " + (currentBranch || "Global")); 

        $.ajax({
            url: 'transactions_users.php', 
            type: 'GET',
            dataType: 'json',
            data: {
                page: page,
                transactionType: transactionType,
                transactionUser: transactionUser,
                startDate: startDate,
                endDate: endDate,
                branch_code: currentBranch,
                _t: new Date().getTime()
            },
            cache: false,
            success: function(response) {
                $('#transactionTableBody').empty();

                // [FIX] Update User Dropdown ONLY if we are viewing 'All' users 
                // (This prevents the filter options from disappearing while you are using them)
                if (transactionUser === "" && response.available_users && response.available_users.length > 0) {
                    let userSelect = $('#transactionUser');
                    let currentVal = userSelect.val();
                    
                    // Clear only if we have new data to show, keep "All" option
                    userSelect.find('option:not(:first)').remove(); 
                    
                    $.each(response.available_users, function(index, username) {
                        // Don't duplicate if already exists (safeguard)
                        if (userSelect.find("option[value='" + username + "']").length === 0) {
                            userSelect.append(new Option(username, username));
                        }
                    });
                    
                    // Restore selection if valid
                    userSelect.val(currentVal);
                }

                if (response && response.transactions && response.transactions.length > 0) {
                    $.each(response.transactions, function(index, transaction) {
                        var profitLossStyle = transaction.profit_loss > 0 ?
                            'style="color: green; font-weight: bold;"' :
                            'style="color: red; font-weight: bold;"';

                        var row = '<tr>';
                        row += '<td><input type="checkbox" class="export-checkbox" data-id="' + transaction.transaction_id + '"></td>';
                        row += '<td>' + transaction.transaction_group_id + '</td>';
                        // Display the formatted username (with location context)
                        row += '<td>' + transaction.username + '</td>'; 
                        row += '<td>' + transaction.item_name + '</td>';
                        row += '<td>' + transaction.transaction_date + '</td>';
                        row += '<td>' + transaction.transaction_type + '</td>';
                        row += '<td>' + transaction.quantity + '</td>';
                        row += '<td>' + CURRENCY + parseFloat(transaction.total_amount).toFixed(2) + '</td>';
                        row += '<td>' + CURRENCY + parseFloat(transaction.sold_at).toFixed(2) + '</td>';
                        row += '<td>' + CURRENCY + parseFloat(transaction.purchase_price).toFixed(2) + '</td>';
                        row += '<td ' + profitLossStyle + '>' + CURRENCY + parseFloat(transaction.profit_loss).toFixed(2) + '</td>';
                        row += '<td>' + (transaction.modified_purchase_time || '00:00:00') + '</td>';
                        row += '<td>' + (transaction.modified_adjustment_time || '00:00:00') + '</td>';
                        row += '</tr>';

                        $('#transactionTableBody').append(row); 
                    });

                    $('#totalSales_').text("Total Sales: " + CURRENCY + parseFloat(response.total_sales || 0).toFixed(2));
                    $('#totalProfits').text("Total Profit: " + CURRENCY + parseFloat(response.total_profit || 0).toFixed(2));
                    $('#totalLosses').text("Total Loss: " + CURRENCY + parseFloat(response.total_loss || 0).toFixed(2));

                    updatePagination(response.total_pages, page);
                } else {
                    $('#transactionTableBody').html('<tr><td colspan="13" class="text-center">No transactions found for this branch selection.</td></tr>');
                    $('#totalSales_').text("Total Sales: " + CURRENCY + "0.00");
                    $('#totalProfits').text("Total Profit: " + CURRENCY + "0.00");
                    $('#totalLosses').text("Total Loss: " + CURRENCY + "0.00");
                    updatePagination(0, page);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error fetching transactions:", error);
                $('#transactionTableBody').html('<tr><td colspan="13" class="text-center">Error fetching transactions.</td></tr>');
            }
        });
    }
    



// Handle 'Select All' checkbox toggle
$(document).on('change', '#selectAll', function() {
  let isChecked = $(this).prop('checked');
  $('.export-checkbox').prop('checked', isChecked);
});

// Ensure individual checkboxes update 'Select All' checkbox correctly
$(document).on('change', '.export-checkbox', function() {
  let totalCheckboxes = $('.export-checkbox').length;
  let checkedCheckboxes = $('.export-checkbox:checked').length;

  // If all individual checkboxes are checked, also check #selectAll
  $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);
});

// for bulk removal of transacted items
$(document).on('click', '#removeSelectedTransactions', function () {
    let selectedTransactions = [];

    $('.export-checkbox:checked').each(function () {
        selectedTransactions.push($(this).data('id'));
    });

    if (selectedTransactions.length === 0) {
        Swal.fire("No Selection", "Please select at least one transaction to remove.", "warning");
        return;
    }

    // [FIX] Strict Online Check
    if (!navigator.onLine) {
        Swal.fire({
            icon: 'error',
            title: 'Offline',
            text: 'You must be online to remove transactions to ensure stock consistency across branches.'
        });
        return;
    }

    // [FIX] Resolve Context
    var currentBranch = '';
    if (typeof ACTIVE_BRANCH_CONTEXT !== 'undefined') {
        currentBranch = ACTIVE_BRANCH_CONTEXT;
    } else {
        const urlParams = new URLSearchParams(window.location.search); 
        currentBranch = urlParams.get('branch_uuid') || '';
    }

    Swal.fire({
        title: "Are you sure?",
        text: "This will mark selected transactions as inactive and RESTORE stock quantity.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "Yes, remove them!"
    }).then((result) => {
        if (result.isConfirmed) {
            
            // Show loading
            Swal.fire({ 
                title: 'Processing...', 
                text: 'Connecting to branch database...', 
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); } 
            });

            $.ajax({
                url: 'removeTransItems.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    removeTransactions: true,
                    transactionIds: selectedTransactions,
                    branch_code: currentBranch // Pass Context
                },
                success: function (response) {
                    if (response.success) {
                        Swal.fire("Removed!", response.message, "success");
                        fetchTransactions(1); // Refresh transaction table
                    } else {
                        Swal.fire("Error!", response.message, "error");
                    }
                },
                error: function (xhr) {
                    console.error(xhr.responseText);
                    Swal.fire("Error!", "Failed to connect to server.", "error");
                }
            });
        }
    });
});


// Function to export selected transactions to Excel
function exportToExcel() {
  let selectedData = [];

  $('#transactionTableBody input.export-checkbox:checked').each(function() {
      let row = $(this).closest('tr');
      let rowData = {
          "Transaction Group ID": row.find("td:eq(1)").text(),
          "User": row.find("td:eq(2)").text(),
          "Item": row.find("td:eq(3)").text(),
          "Date": row.find("td:eq(4)").text(),
          "Type": row.find("td:eq(5)").text(),
          "Quantity": row.find("td:eq(6)").text(),
          "Total Amount": row.find("td:eq(7)").text(),
          "Sold At": row.find("td:eq(8)").text(),
          "Cost Price": row.find("td:eq(9)").text(),
          "Profit/Loss": row.find("td:eq(10)").text(),
          "Purchase Price Updated_At": row.find("td:eq(11)").text(),
          "WHL/RTL Updated_At": row.find("td:eq(12)").text()
      };
      selectedData.push(rowData);
  });

  if (selectedData.length === 0) {
      alert("Please select at least one transaction to export.");
      return;
  }

  // Convert JSON to a SheetJS worksheet and export
  let ws = XLSX.utils.json_to_sheet(selectedData);
  let wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "Transactions");
  XLSX.writeFile(wb, "Transactions_Report.xlsx");
}

// Attach export function to button click
$(document).on('click', '#exportExcelBtn', exportToExcel);



// Event handler for filter fields
$('#transactionType,#startDate, #endDate').change(function() {
    fetchTransactions(1); // Reset to the first page on filter change
});

// Debounce for User Input (Wait 500ms after typing stops)
    let typingTimer;
    $('#transactionUser').on('keyup', function() {
        clearTimeout(typingTimer);
        typingTimer = setTimeout(function() {
            fetchTransactions(1);
        }, 500);
    });

/* Handle the filter by transactionGroup ID */
$('#searchTransactionGroup').on('input', function () {
  var searchQuery = $(this).val().toLowerCase().trim();
  var totalSalesSearched = 0;
  var totalProfitSearched = 0;
  var totalLossSearched = 0;
  var hasSearchQuery = searchQuery !== ""; // Ensure input is not empty

  // Check if there are matching results
  var foundMatch = false;

  $('#transactionTableBody tr').each(function () {
      var transactionGroupID = $(this).find('td').eq(1).text().toLowerCase(); // Correct column index (1)
      var totalAmount = parseFloat($(this).find('td').eq(7).text().replace(CURRENCY, '').replace(/,/g, '')) || 0;
      var profitLoss = parseFloat($(this).find('td').eq(10).text().replace(CURRENCY, '').replace(/,/g, '')) || 0;

      if (transactionGroupID.includes(searchQuery)) {
          $(this).show();
          foundMatch = true; // Set flag for matching rows
          totalSalesSearched += totalAmount;
          if (profitLoss > 0) {
              totalProfitSearched += profitLoss;
          } else {
              totalLossSearched += Math.abs(profitLoss);
          }
      } else {
          $(this).hide();
      }
  });

  // If no matches, show "No Transactions Found"
  if (!foundMatch) {
      $('#transactionTableBody').html('<tr><td colspan="13" class="text-center">No matching transactions found.</td></tr>');
  }

  // Update totals dynamically
  if (hasSearchQuery) {
      $('#totalSales_').hide();
      $('#aggregatedTotal').text(CURRENCY + totalSalesSearched.toFixed(2)).parent().show();
      $('#totalProfits').text("Total Profit: " + CURRENCY + totalProfitSearched.toFixed(2));
      $('#totalLosses').text("Total Loss: " + CURRENCY + totalLossSearched.toFixed(2));
  } else {
      $('#totalSales_').show();
      $('#aggregatedTotal').parent().hide();
      fetchTransactions(1); // Reload transactions to restore original values
  }
});

  // Initial fetch of transactions
  fetchTransactions(1); // Fetch page 1 initially

// Load users for the "User" filter 
loadUsers();

// Function to load users into the filter dropdown based on Branch Context
function loadUsers() {
    // 1. Resolve Branch Context
    var currentBranch = '';
    if (typeof ACTIVE_BRANCH_CONTEXT !== 'undefined') {
        currentBranch = ACTIVE_BRANCH_CONTEXT;
    } else {
        const urlParams = new URLSearchParams(window.location.search);
        currentBranch = urlParams.get('branch_uuid') || '';
    }

    console.log("Loading Users for Branch: " + (currentBranch || "Global"));

    $.ajax({
        url: 'load_users.php', // PHP endpoint
        type: 'GET', 
        dataType: 'json', 
        data: {
            branch_code: currentBranch // <--- Pass the branch context here
        },
        success: function(response) {
            $('#transactionUser').empty();
            $('#transactionUser').append('<option value="">All</option>');
            
            if (response.users && response.users.length > 0) {
                $.each(response.users, function(index, user) {
                    $('#transactionUser').append('<option value="' + user.username + '">' + user.username + '</option>');
                });
            } else {
                console.log("No users found for this branch context.");
            }
        },
        error: function(xhr, status, error) {
            console.error("Error loading users:", error);
        }
    });
}

 // Function to update pagination links
function updatePagination(totalPages, currentPage) {
  var paginationContainer = $('#paginationContainer');
  paginationContainer.empty();

  // Define the maximum number of visible page links
  var maxVisiblePages = 3; // Adjust this value as needed

  // Create previous button
  var previousButton = $('<a>').attr('href', '#').addClass('page-link').text('Previous');
  if (currentPage === 1) {
    previousButton.addClass('disabled');
    previousButton.addClass('text-danger');
  } else {
    previousButton.click(function(e) {
      e.preventDefault();
      var page = currentPage - 1;
      fetchTransactions(page);
    });
  }

  // Create next button
  var nextButton = $('<a>').attr('href', '#').addClass('page-link').text('Next');
  if (currentPage === totalPages) {
    nextButton.addClass('disabled');
    nextButton.addClass('text-danger');
  } else {
    nextButton.click(function(e) {
      e.preventDefault();
      var page = currentPage + 1;
      fetchTransactions(page);
    });
  }

  // Create pagination container
  var paginationList = $('<ul>').addClass('pagination justify-content-center');
  var previousListItem = $('<li>').addClass('page-item').append(previousButton);
  var nextListItem = $('<li>').addClass('page-item').append(nextButton);

  // Calculate the range of visible page links
  var startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
  var endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

  // Add ellipsis before the first page if needed
  if (startPage > 1) {
    var firstPageLink = $('<a>').attr('href', '#').addClass('page-link').text('1');
    firstPageLink.click(function(e) {
      e.preventDefault();
      fetchTransactions(1);
    });
    var firstPageListItem = $('<li>').addClass('page-item').append(firstPageLink);
    paginationList.append(firstPageListItem);
    if (startPage > 2) {
      paginationList.append($('<li>').addClass('page-item').append($('<span>').addClass('page-link').text('...')));
    }
  }

  // Create page links
  for (var i = startPage; i <= endPage; i++) {
    var link = $('<a>').attr('href', '#').addClass('page-link').text(i);
    if (i === currentPage) {
        link.addClass('active'); // Add 'active' class to the current page link
    } 


    // Bind click event to fetch transactions for the clicked page 
    link.click(function(e) {
      e.preventDefault();
      var page = parseInt($(this).text());
      fetchTransactions(page);
    });

    var listItem = $('<li>').addClass('page-item').append(link);
    paginationList.append(listItem);
  }

  // Add ellipsis after the last page if needed
  if (endPage < totalPages) {
    if (endPage < totalPages - 1) {
      paginationList.append($('<li>').addClass('page-item').append($('<span>').addClass('page-link').text('...')));
    }
    var lastPageLink = $('<a>').attr('href', '#').addClass('page-link').text(totalPages);
    lastPageLink.click(function(e) {
      e.preventDefault();
      fetchTransactions(totalPages);
    });
    var lastPageListItem = $('<li>').addClass('page-item').append(lastPageLink);
    paginationList.append(lastPageListItem);
  }

  paginationList.prepend(previousListItem);
  paginationList.append(nextListItem);
  paginationContainer.append(paginationList);
}


  // Fetch transactions for the initial page
  fetchTransactions(1);
});
