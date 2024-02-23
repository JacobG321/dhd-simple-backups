document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('file-upload-form');
    if (form) {
        form.onsubmit = function(event) {
            event.preventDefault();
            var file = document.getElementById('backup_file').files[0];
            var chunkSize = 1024 * 1024; // 1MB chunk size
            var chunks = Math.ceil(file.size / chunkSize);
            var currentChunk = 0;
            
            function uploadChunk(start) {
                var end = start + chunkSize;
                var chunk = file.slice(start, end);
                var formData = new FormData();
                formData.append('file_chunk', chunk, file.name);
                formData.append('chunk_number', currentChunk);
                formData.append('total_chunks', chunks);
                formData.append('action', 'sb_import_action_chunk');
                
                fetch(adminAjaxUrl, { // adminAjaxUrl to be defined globally or passed into this script
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }).then(function(response) {
                    return response.json(); // Assuming the server responds with JSON
                }).then(function(data) {
                    if (data.success) {
                        if (currentChunk < chunks - 1) {
                            currentChunk++;
                            uploadChunk(currentChunk * chunkSize);
                        } else {
                            alert('Upload complete');
                            // Optionally redirect or update UI
                        }
                    } else {
                        alert('Server error during upload: ' + data.data);
                    }
                }).catch(function(error) {
                    console.error('Error uploading chunk:', error);
                    alert('Error during upload. Please try again.');
                });
            }
            
            uploadChunk(0);
        };
    }
});
