$(document).ready(function() {
  
    // Function to fetch and display the items 
    function viewItems(page) {
      $.ajax({
        url: 'view_items.php',//php endpoint
        type: 'GET',
        dataType: 'json',
        data: {
          page: page,
       
        },
        success: function(response) {
          // Clear the table body
          $('#itemsTableBody').empty();
  
          // Loop through the items data and add each item to the table
          $.each(response.items, function(index, item) {
            var row = '<tr>';
            row += '<td>' + item.item_id + '</td>';
            row += '<td>' + item.item_unique_no + '</td>';
            row += '<td>' + item.item_name + '</td>';
            row += '<td>' + item.item_description + '</td>';
            row += '<td>' + item.purchase_price + '</td>';
            row += '<td>' + item.wholesale + '</td>'; 
            row += '<td>' + item.retail + '</td>';
            row += '<td>' + item.status + '</td>';
            row += '<td>' + item.category + '</td>';
            row += '<td>' + item.quantity_in_stock + '</td>';
            row += '<td>';
            row += '<button class="btn btn-primary btn-sm mr-2 edit-item" data-item-id="' + item.item_id + '">Edit</button>';
            row += '<button class="btn btn-danger btn-sm delete-item" data-item-id="' + item.item_id + '">Delete</button>';
            row += '</td>';
            row += '</tr>';
  
            $('#itemsTableBody').append(row); 
           // console.log(item.item_unique_no) 
          });

          
            // Update the pagination links
        updatePagination(response.total_pages, page);

        },
        error: function(xhr, status, error) {
          console.log(error);
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
      viewItems(page);
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
      viewItems(page);
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
      viewItems(1);
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
      link.addClass('active');
    }

    // Bind click event to fetch transactions for the clicked page
    link.click(function(e) {
      e.preventDefault();
      var page = parseInt($(this).text());
      viewItems(page);
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
      viewItems(totalPages);

    });
    var lastPageListItem = $('<li>').addClass('page-item').append(lastPageLink);
    paginationList.append(lastPageListItem);
  }

  paginationList.prepend(previousListItem);
  paginationList.append(nextListItem);
  paginationContainer.append(paginationList);
}


  
    // Call the viewItems function to display the items on page load
    viewItems(1);

    
     
$(document).ready(function() {
    
    // --- 1. Centralized Form Submission Logic ---
    function processItemSubmission(targetBranchCode) {
        // Gather Data
        var formData = {
            itemName: $('#itemName').val().trim(),
            itemDescription: $('#itemDescription').val().trim(),
            supplierInfo: $('#supplierInfo').val().trim(),
            invoiceNumber: $('#invoiceNumber').val().trim(),
            datePurchased: $('#datePurchased').val().trim(),
            itemPrice: parseFloat($('#itemPrice').val()).toFixed(2),
            wholesale: parseFloat($('#wholesale_prc').val()).toFixed(2),
            retail: parseFloat($('#retail_prc').val()).toFixed(2),
            itemQuantity: parseInt($('#itemQuantity').val()),
            itemUniqueNo: parseInt($('#itemUniqueNo').val()),
            expirationDate: $('#expirationDate').val().trim(),
            categorySelect: $('#categorySelect').val().trim()
        };

        // Validation
        if (!formData.itemName || !formData.itemPrice || !formData.itemQuantity || !formData.itemUniqueNo || !formData.expirationDate || !formData.categorySelect) {
            Toastify({ text: 'Please fill all required fields.', style: { background: 'linear-gradient(to right, #FFA0A0, #A0A0FF)' } }).showToast();
            return;
        }

        // Sanitize (Basic client-side)
        Object.keys(formData).forEach(key => {
            if(typeof formData[key] === 'string') formData[key] = escapeHtml(formData[key]);
        });

        // Add Target Branch if exists
        if(targetBranchCode) {
            formData.target_branch_code = targetBranchCode;
        }

        // Disable Button
        const $btn = $('button[type="submit"]');
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

        // AJAX Submission
        $.ajax({
            url: 'add_item.php',
            type: 'POST',
            dataType: 'json',
            data: formData,
            success: function(response) {
                if (response.success) {
                    Toastify({ 
                        text: response.message || 'Item added successfully!', 
                        style: { background: 'linear-gradient(to right, #00b09b, #96c93d)' } 
                    }).showToast();

                    // If we added to CURRENT branch, refresh table. 
                    // If remote, we don't refresh because it won't be in local DB.
                    if (!targetBranchCode || targetBranchCode === 'HEAD_OFFICE') { // Assuming HEAD_OFFICE is local
                         if(typeof viewItems === 'function') viewItems(1);
                    }

                    $('#addItemForm')[0].reset();
                    $('#itemBranchModal').modal('hide');
                } else {
                    Toastify({ text: response.message || 'Error adding item.', style: { background: 'red' } }).showToast();
                }
            },
            error: function(xhr, status, error) {
                Toastify({ text: 'Connection Error: ' + error, style: { background: 'red' } }).showToast();
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
                $('#confirmItemAddBtn').prop('disabled', false).text('Confirm & Add Item');
            }
        });
    }

// --- 2. Intercept Add Item Form Submission ---
    $('#addItemForm').on('submit', function(e) {
        e.preventDefault();
        
        // [FIX] Resolve Context dynamically
        var targetContext = (typeof ACTIVE_BRANCH_CONTEXT !== 'undefined') ? ACTIVE_BRANCH_CONTEXT : USER_SESSION_BRANCH;
        
        // [FIX] Dynamic Remote Check (User's Session vs Target)
        var isRemote = (targetContext !== USER_SESSION_BRANCH);

        // STRICT ONLINE CHECK (Initiator)
        if (isRemote && !navigator.onLine) {
            Toastify({ text: "Offline: Cannot add items to remote branch.", style: { background: "red" } }).showToast();
            return;
        }

        if (true) { 
            // Populate Dropdown
            $.ajax({
                url: 'get_branches.php', 
                dataType: 'json',
                success: function(branches) {
                    let $select = $('#targetBranchSelect');
                    $select.empty();
                    
                    // [FIX] Removed hardcoded HEAD_OFFICE append.
                    // We iterate ALL branches returned by the API.
                    $.each(branches, function(i, branch) {
                        $select.append(`<option value="${branch.branch_code}" data-status="${branch.status}">${branch.branch_name}</option>`);
                    });

                    // Auto-Select the Active Context
                    $select.val(targetContext);
                }
            });
            
            $('#itemBranchModal').modal('show');

        } else {
            processItemSubmission(null);
        }
    });

    // --- 3. Modal Confirm Button ---
    $('#confirmItemAddBtn').on('click', function() {
        var selectedBranch = $('#targetBranchSelect').val();
        
  // UI Feedback
        $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');
        processItemSubmission(selectedBranch);
    });


    // Helper
    function escapeHtml(str) {
        return str.replace(/[&<>"'\/]/g, function (s) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '/': '&#47;' }[s];
        });
    }
});
  

})
  