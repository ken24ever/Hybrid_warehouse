<!-- start of modal for purchase invoice -->
    <div id="purchaseInvoiceModal" class="modal dark-theme light-theme navbar-dark">
        <div class="modal-content dark-theme light-theme navbar-dark">
            <div class="modal-header dark-theme light-theme navbar-dark">
                <h2>Purchase Invoices</h2>
                <span class="close-button" id="closePurchaseInvoiceModal">&times;</span>
            </div>
            <div class="modal-body dark-theme light-theme navbar-dark">
        <div class="filter-container">
    <div class="relative rounded-md shadow-sm dark-theme light-theme navbar-dark">
    <!--     <input
            type="text"
            id="dateFilter"
            class="form-control pr-10 focus:ring-blue-500 focus:border-blue-500"
            placeholder="Filter by Purchase Date (YYYY-MM-DD)"
        /> -->
        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none dark-theme light-theme navbar-dark">
            <i class="fas fa-calendar text-gray-500 sm:text-sm"></i>
        </div>
    </div>

    <div class="relative rounded-md shadow-sm dark-theme light-theme navbar-dark">
   <!--      <input
            type="text"
            id="invoiceFilter"
            class="form-control pr-10 focus:ring-blue-500 focus:border-blue-500"
            placeholder="Filter by Invoice Number"
        /> -->
        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none dark-theme light-theme navbar-dark">
            <i class="fas fa-file-invoice text-gray-500 sm:text-sm"></i>
        </div>
    </div>

    <div class="relative rounded-md shadow-sm dark-theme light-theme navbar-dark">
  <!--       <input
            type="text"
            id="supplierFilter"
            class="form-control pr-10 focus:ring-blue-500 focus:border-blue-500"
            placeholder="Filter by Supplier"
        /> -->
        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
            <i class="fas fa-truck text-gray-500 sm:text-sm"></i>
        </div>
    </div>
</div>

                <div class="table-responsive dark-theme light-theme navbar-dark">
                  <table id="purchaseInvoiceTable" class="display dark-theme light-theme navbar-dark">
                      <thead>
                          <tr>
                              <th>Invoice Number</th>
                              <th>Purchase Date</th>
                              <th>Supplier Info</th>
                              <th>Item Name</th>
                              <th>Description</th>
                              <th>Quantity</th>
                              <th>Price</th>
                          </tr>
                      </thead>
                      <tbody>
                          </tbody>
                  </table>
                </div>
            </div>
        </div>
    </div>
<!-- ends purchase invoice -->