$(document).ready(function () {
    
    // --- 1. Centralized Upload Function ---
    function processBulkUploadSubmission(targetBranchCode) {
        var fileInput = $('#excelFile');
        var file = fileInput.get(0).files[0];
        
        // Final Validation (Double Check)
        if (!file) { showToast('Please select a file.', 'danger'); return; } 

        // UI Updates
        $('#uploadProgress').show();
        $('#uploadBar').css('width', '10%').attr('aria-valuenow', 10).text('Initializing...');
        $('#confirmBulkUploadBtn').prop('disabled', true).text('Processing...');

        var formData = new FormData($('#uploadFileForm')[0]);
        
        // Attach Target Branch Code if exists
        if (targetBranchCode) {
            formData.append('target_branch_code', targetBranchCode);
        }

        $.ajax({
            url: 'upload_items.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            xhr: function () {
                let xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function (evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = Math.round((evt.loaded / evt.total) * 100);
                        $('#uploadBar').css('width', percentComplete + '%').attr('aria-valuenow', percentComplete).text(percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            success: function (response) {
                $('#uploadProgress').hide();
                $('#bulkUploadBranchModal').modal('hide');
                $('#confirmBulkUploadBtn').prop('disabled', false).text('Confirm & Upload');

                if (response.success) {
                    showToast(`${response.message} (Time: ${response.execution_time}s)`, 'success');
                    if (response.errors?.length) {
                        Swal.fire('Partial Success', response.errors.join('\n'), 'warning');
                    }
                    $('#uploadFileForm')[0].reset();
                } else {
                    showToast(response.message, 'danger');
                }
            },
            error: function () {
                $('#uploadProgress').hide();
                $('#bulkUploadBranchModal').modal('hide');
                $('#confirmBulkUploadBtn').prop('disabled', false).text('Confirm & Upload');
                
                showToast('An error occurred while uploading. Try again.', 'danger');
            }
        });
    }

   // --- 2. Intercept Form Submission (Not just a click) ---
    $('#uploadFileForm').on('submit', function(e) {
        e.preventDefault(); // Prevent default browser submit
        
        var fileInput = $('#excelFile');
        var file = fileInput.get(0).files[0];

        // 1. Validation: File Selected?
        if (!file) {
            Toastify({ 
                text: "Please select a file first.", 
                style: { background: "red" } 
            }).showToast();
            return;
        }

        // [FIX] Resolve Context
        var targetContext = (typeof ACTIVE_BRANCH_CONTEXT !== 'undefined') ? ACTIVE_BRANCH_CONTEXT : USER_SESSION_BRANCH;

        // Open the Modal
        if (true) { 
            $.ajax({
                url: 'get_branches.php', 
                dataType: 'json',
                success: function(branches) {
                    let $select = $('#bulkTargetBranchSelect');
                    $select.empty();
                    
                    $.each(branches, function(i, branch) {
                        $select.append(`<option value="${branch.branch_code}" data-status="${branch.status}">${branch.branch_name}</option>`);
                    });

                    $select.val(targetContext);
                }
            });
            
            $('#bulkUploadBranchModal').modal('show');

        } else {
            processBulkUploadSubmission(null);
        }
    });

// --- 3. Modal Confirm Button (Validation Removed) ---
    $('#confirmBulkUploadBtn').on('click', function() {
        var selectedBranch = $('#bulkTargetBranchSelect').val();
        
        // Disable button to prevent double-click
        $(this).prop('disabled', true).text('Uploading...');
        
        // Pass to backend for strict validation
        processBulkUploadSubmission(selectedBranch);
    });
  
  

    function showToast(message, type = 'success') {
        Toastify({
            text: message,
            duration: 6000,
            gravity: 'top',
            close: true,
            style: {
                background: type === 'success'
                    ? 'linear-gradient(to right, #00b09b, #96c93d)'
                    : 'linear-gradient(to right, #ff5f6d, #ffc371)'
            }
        }).showToast();
    }
});