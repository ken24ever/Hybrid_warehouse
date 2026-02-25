$(document).ready(function() {
    // Add "Reporting" button to each row
    function addReportingButton(item) {
      return '<button class="btn btn-info btn-sm reporting-item" data-item-id="' + item.item_id + '" data-item-name="' + item.item_name + '">Reporting</button>';
    }

   // Function to fetch and display the items
function viewItems(page) {
  $.ajax({
      url: 'view_items.php',
      type: 'GET',
      dataType: 'json',
      data: {
          page: page,
      },
      success: function (response) {
          // Clear the table body
          $('#itemsTableBody').empty();

          // Variables for alerts
          let lowStockCount = 0;
          let outOfStockCount = 0;

          // Loop through the items data and add each item to the table
          $.each(response.items, function (index, item) {
              // Determine row color based on stock quantity
              let rowClass = "";
              if (item.quantity_in_stock == 0) {
                  rowClass = "table-danger"; // Red for out of stock
                  outOfStockCount++;
              } else if (item.quantity_in_stock < 20) {
                  rowClass = "table-warning"; // Yellow for low stock
                  lowStockCount++;
              }

              // Add item row
              let row = `<tr class="${rowClass}">`;
              row += `<td>${item.item_id}</td>`;
              row += `<td>${item.item_unique_no}</td>`;
              row += `<td>${item.item_name}</td>`;
              row += `<td>${item.item_description}</td>`;
              row += `<td>${item.purchase_price}</td>`;
              row += `<td>${item.sale_price}</td>`;
              row += `<td>${item.status}</td>`;
              row += `<td>${item.category}</td>`;
              row += `<td>${item.quantity_in_stock}</td>`;
              row += `<td>`;
              row += `<button class="btn btn-primary btn-sm mr-2 edit-item" data-item-id="${item.item_id}">Edit</button>`;
              row += `<button class="btn btn-danger btn-sm delete-item" data-item-id="${item.item_id}">Delete</button>`;
              row += addReportingButton(item); // Add the reporting button
              row += `</td>`;
              row += `</tr>`;

              $('#itemsTableBody').append(row);
          });

          // Update the pagination links
          updatePagination(response.total_pages, page);

          // Display stock alerts
          displayStockAlerts(lowStockCount, outOfStockCount);
      },
      error: function (xhr, status, error) {
          console.error("Error:", error);
      },
  });
}

// Function to display stock alerts
function displayStockAlerts(lowStockCount, outOfStockCount) {
  let alertMessage = "";

  if (outOfStockCount > 0) {
      alertMessage += `<div class="alert alert-danger">⚠️ ${outOfStockCount} item(s) are out of stock!</div>`;
  }
  if (lowStockCount > 0) {
      alertMessage += `<div class="alert alert-warning">⚠️ ${lowStockCount} item(s) are running low (less than 20 in stock)!</div>`;
  }

  if (alertMessage) {
      $('#stockAlerts').html(alertMessage).fadeIn();
  } else {
      $('#stockAlerts').fadeOut();
  }
}

// Filter functionality for row colors
$('#filterByStatus').change(function () {
  let filter = $(this).val();

  $('#itemsTableBody tr').each(function () {
      let rowClass = $(this).attr('class');
      if (
          (filter === "amber" && rowClass.includes("table-warning")) ||
          (filter === "red" && rowClass.includes("table-danger")) ||
          filter === "all"
      ) {
          $(this).show();
      } else {
          $(this).hide();
      }
  });
});

// Initial fetch of items
viewItems(1); // Fetch page 1 initially


    // Event listener for "Reporting" button
  $(document).on('click', '.reporting-item', function () {
    var itemName = $(this).data('item-name')
    var itemId = $(this).data('item-id');


    $('#reportingModal').data('item-id', itemId).modal('show');
    $('.modal-title').html("Item Reporting for<strong style='font-size:18px;'> "+itemName + "</strong>");

    //clear input upon clicking
    $("#specificDate").val('');
    $("#specificMonth").val('');

    // Update modal content with report data
    $('#reportTotalAmount').text("");
    $('#reportProfit').text("");
    $('#reportQuantitySold').text("");
    $('#reportQuantityStock').text("");

    // Update chart with data
    Highcharts.chart('reportChart', {
      title: { text: 'Sales Comparison: Current vs Previous Month' },
      xAxis: { categories: ['Previous Month', 'Current Month'] },
      yAxis: { title: { text: 'Amount & Quantity' } },
      series: [
        {
          name: 'Quantity Sold',
          data: "",
        },
        {
          name: 'Total Amount', 
          data: "",
        },
      ],
    });
  });

  // Submit event for generating the report
  $('#generateReport').click(function () {
    var itemId = $('#reportingModal').data('item-id');
    var date = $('#specificDate').val(); // Day selection
    var month = $('#specificMonth').val(); // Month selection

    $.ajax({
      url: 'generate_report.php',
      type: 'POST',
      dataType: 'json',
      data: {
        itemId: itemId,
        date: date,
        month: month,
      },
      success: function (response) {
        if (response.success) {
          // Update modal content with report data
          $('#reportTotalAmount').text(response.totalAmount);
          $('#reportProfit').text(response.profit);
          $('#reportQuantitySold').text(response.quantitySold);
          $('#reportQuantityStock').text(response.quantityStock);

          // Update chart with data
          Highcharts.chart('reportChart', {
            title: { text: 'Sales Comparison: Current vs Previous Month' },
            xAxis: { categories: ['Previous Month', 'Current Month'] },
            yAxis: { title: { text: 'Amount & Quantity' } },
            series: [
              {
                name: 'Quantity Sold',
                data: response.chartData.quantity,
              },
              {
                name: 'Total Amount',
                data: response.chartData.amount,
              },
            ],
          });
        } else {
          alert(response.message);
        }
      },
      error: function (xhr, status, error) {
        console.log(error);
      },
    });
  });

  viewItems(1); // Load items on page load

    
  
// Add event listener to the filter input
$(document).on('input', '#category', function() {
  var category = $(this).val().trim(); // Get the category value and remove leading/trailing spaces
  filterTable(category);
});

// Function to filter the table based on the category
function filterTable(category) {
  var tableRows = $('#itemsTableBody').find('tr'); // Get all table rows in the tbody
  if (category === '') {
    // Show all rows if no category is entered
    tableRows.show();
  } else {
    // Hide rows that don't match the entered category
    tableRows.each(function() {
      var itemName = $(this).find('td:nth-child(2)').text().trim(); // Get the item name and remove leading/trailing spaces
      if (itemName.toLowerCase().indexOf(category.toLowerCase()) === -1) {
        // If the category doesn't match, hide the row
        $(this).hide();
      } else {
        // If the category matches, show the row
        $(this).show();
      }
    });
  }
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

   
  
    // Add event listeners for edit and delete buttons
    $(document).on('click', '.edit-item', function() {
      var itemId = $(this).data('item-id');
  
      // Make an AJAX request to get the item details
      $.ajax({
        url: 'get_item_details.php',
        type: 'GET',
        dataType: 'json',
        data: {
          itemId: itemId
        },
        success: function(response) {
          // Populate the edit form fields with the item details
          $(' #itemID').val(response.item_id);
          $(' #itemName').val(response.item_name);
          $(' #itemDescription').val(response.item_description);
          $(' #itemPrice').val(response.purchase_price);
          $(' #itemSalePrice').val(response.sale_price);
          $(' #itemQuantity').val(response.quantity_in_stock);
          $(' #itemStatus').val(response.status);
          $(' #category_Select').val(response.category);
          // Show the edit form
          $('#editItemModal').show();

          
        },
        error: function(xhr, status, error) {
          console.log(error);
        }
      });
    });

      // Add event listeners for closing the modal button
      $(document).on('click', '.close', function() {
              // hide the edit form
              $('#editItemModal').hide();
      })//end of close modal

  
    // Submit the edit form
    $('#editItemForm').on('submit', function(event) {
      event.preventDefault();
  
      // Get the form data
      var formData = $(this).serialize();
  
      // Make an AJAX request to update the item details
      $.ajax({
        url: 'update_item.php',
        type: 'POST',
        dataType: 'json',
        data: formData,
        success: function(response) {
          if (response.success) {
            // If the update is successful, hide the edit form and refresh the items table
            $('#editItemModal').hide();
            Toastify({
              text: response.message,
              duration: 5000,
              gravity: 'top',
              close: true,
              style: {
                background: 'linear-gradient(to right, #FFA0A0, #B88AFF, #A0A0FF)',
              }
            }).showToast();
            viewItems(1); 
          } else {
            // If there is an error, display the error message
            //alert('Error updating item: ' + response.message);
            Toastify({
              text: response.message,
              duration: 5000,
              gravity: 'bottom',
              close: true,
              style: {
                background: 'linear-gradient(to right, #FFA0A0, #B88AFF, #A0A0FF)',
              }
            }).showToast();
          }
        },
        error: function(xhr, status, error) {
          console.log(error);
        }
      });
    });
  
    // Delete item
    $('#itemsTableBody').on('click', '.delete-item', function() {
      var itemId = $(this).data('item-id');

             // Confirm before deleting
    Swal.fire({
      title: 'Are You Sure You Want To Delete Item With ID :'+itemId+'?',
      html: '<h6 style="color:red">This Action Cannot Be Undone If You Proceed!</h6>',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: 'darkGreen',
      cancelButtonColor: 'darkRed',
      confirmButtonText: 'Yes, Submit!',
      showLoaderOnConfirm: true,
  }).then((result) => {
          if (result.isConfirmed)
             {
                    // Make an AJAX request to delete the item
        $.ajax({
          url: 'delete_item.php',
          type: 'POST',
          dataType: 'json',
          data: {
            itemId: itemId
          },
          success: function(response) {
            if (response.success) {
              // If the delete is successful, refresh the items table
              //alert(response.message)
              Toastify({
                text: response.message,
                duration: 5000,
                gravity: 'top',
                close: true,
                style: {
                  background: 'linear-gradient(to right, #FFA0A0, #B88AFF, #A0A0FF)',
                }
              }).showToast();
              viewItems(1);
            } else {
              // If there is an error, display the error message
              //alert('Error deleting item: ' + response.message);
              Toastify({
                text: response.message,
                duration: 5000,
                gravity: 'bottom',
                close: true,
                style: {
                  background: 'linear-gradient(to right, #FFA0A0, #B88AFF, #A0A0FF)',
                }
              }).showToast();
            }
          },
          error: function(xhr, status, error) {
            console.log(error);
          }
        });

              }//end of result.isConfirmed
              else
              {
                    // Show display when you click cancel
                    Swal.fire({
                      icon: 'info',
                      html: '<b>You backed out!</b>'
                      });
              }
          });//end of thenables

    });
  });
  

  