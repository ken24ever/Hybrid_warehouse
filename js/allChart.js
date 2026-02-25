$(document).ready(function() {
// Function to fetch data from PHP scripts and render charts
function renderCharts() {
    // Fetch inventory overview data
    $.ajax({
      url: 'get_inventory_overview.php',//php endpoint
      type: 'GET',
      dataType: 'json',
      success: function(response) {
    
        renderInventoryOverviewChart(parseInt(response.totalItems), parseFloat(response.totalValue));
       
      },
      error: function(xhr, status, error) {
        console.log(error);
      }
    });

    // Fetch sales trend data
    $.ajax({
      url: 'get_sales_trend.php',//php endpoint
      type: 'GET',
      dataType: 'json',
      success: function(response) {
        renderSalesTrendChart(response);
      },
      error: function(xhr, status, error) {
        console.log(error);
      }
    });

    // Fetch top selling items data
    $.ajax({
      url: 'get_top_selling_items.php',//php endpoint
      type: 'GET',
      dataType: 'json',
      success: function(response) {
        renderTopSellingItemsChart( response);
       
      },
      error: function(xhr, status, error) {
        console.log(error);
      }
    });

    // Fetch inventory value data
    $.ajax({
      url: 'get_inventory_value.php',//php endpoint
      type: 'GET',
      dataType: 'json',
      success: function(response) {
        renderInventoryValueChart(response);
   
      },
      error: function(xhr, status, error) {
        console.log(error);
      }
    });

    // Fetch item category breakdown data
    $.ajax({
      url: 'get_item_category_breakdown.php',//php endpoint
      type: 'GET',
      dataType: 'json',
      success: function(response) {
        renderItemCategoryBreakdownChart(response);
      },
      error: function(xhr, status, error) {
        console.log(error);
      }
    });
  }

// Function to render Inventory Overview Chart
function renderInventoryOverviewChart(totalItems, totalValue) { 
   
    const categories = ['Total Items', 'Total Value'];
    const data = [parseInt(totalItems), totalValue];
  
    Highcharts.chart('inventoryOverviewChartContainer', {
        chart: {
          type: 'column'
        },
        title: {
          text: 'Inventory Overview'
        },
        xAxis: {
          categories: ['Inventory Overview']
        },
        yAxis: [{
          title: {
            text: 'Total Items'
          }
        }, {
          title: {
            text: 'Total Value'
          },
          opposite: true // Align this axis on the right side of the chart
        }],
        series: [{
          name: 'Total Items',
          data: [totalItems],
          yAxis: 0 // Use the first y-axis for this data
        }, {
          name: 'Total Value',
          data: [totalValue],
          yAxis: 1 // Use the second y-axis for this data
        }]
      });
  }
  

// Function to render Sales Trend Chart
function renderSalesTrendChart(data) {
    const categories = [];
    const totalSales = [];
  
    for (let i = 0; i < data.length; i++) {
      categories.push(data[i].date);
      totalSales.push(parseInt(data[i].totalSales));
    }
  
    Highcharts.chart('salesTrendChartContainer', {
      chart: {
        type: 'line'
      },
      title: {
        text: 'Sales Trend'
      },
      xAxis: {
        categories: categories
      },
      yAxis: {
        title: {
          text: 'Total Sales'
        }
      },
      series: [{
        name: 'Total Sales',
        data: totalSales
      }]
    });
  }
  

  // Function to render Top Selling Items Chart
function renderTopSellingItemsChart(data) {
    const categories = [];
    const totalSold = [];
  
    for (let i = 0; i < data.length; i++) {
      categories.push(data[i].itemName);
      totalSold.push(parseInt(data[i].totalSold));
    }
  
    Highcharts.chart('topSellingItemsChartContainer', {
      chart: {
        type: 'bar'
      },
      title: {
        text: 'Top Selling Items'
      },
      xAxis: {
        categories: categories
      },
      yAxis: {
        title: {
          text: 'Total Sold'
        }
      },
      series: [{
        name: 'Total Sold',
        data: totalSold
      }]
    });
  }
  

// Function to render Inventory Value Chart
function renderInventoryValueChart(data) {
    const formattedData = Object.keys(data).map(key => ({
      name: key,
      y: data[key],
    }));
  
    Highcharts.chart('inventoryValueChartContainer', {
      chart: {
        type: 'pie',
      },
      title: {
        text: 'Inventory Value Breakdown',
      },
      series: [
        {
          name: 'Value',
          data: formattedData,
        },
      ],
    });
  }
  
  

// Function to render Item Category Breakdown Chart
function renderItemCategoryBreakdownChart(data) {
    const categories = [];
    const itemCounts = [];
  
    for (let i = 0; i < data.length; i++) {
      categories.push(data[i].category);
      itemCounts.push(parseInt(data[i].count));
    }
  
    Highcharts.chart('itemCategoryBreakdownChartContainer', {
      chart: {
        type: 'column'
      },
      title: {
        text: 'Item Category Breakdown'
      },
      xAxis: {
        categories: categories
      },
      yAxis: {
        title: {
          text: 'Number of Items'
        }
      },
      series: [{
        name: 'Count',
        data: itemCounts
      }]
    });
  }
  

  // Call the renderCharts function to display the charts on page load
  renderCharts();
  setInterval(renderCharts,50000)
})