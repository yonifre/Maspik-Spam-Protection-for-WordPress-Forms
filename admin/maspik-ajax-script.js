document.addEventListener("DOMContentLoaded", function() {
    document.getElementById("frmContact").addEventListener("submit", function(event) {
        event.preventDefault(); // Prevent the form from submitting normally

        // Show loading spinner or message
        document.getElementById("statusMessage").innerHTML = "<p class='loadingMessage'>Processing...</p>";

        // Your AJAX logic goes here
        var formData = new FormData(this);
        formData.append('action', 'maspik_handle_playground_form'); // Add action to formData

        fetch(ajax_object.ajax_url, {
            method: "POST",
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            // Update status message
            document.getElementById("statusMessage").innerHTML = "<p class='" + data.status + "Message'>" + data.message + "</p>";

            // Update input values and their parent elements based on data
            updateInputElement("note-name", data.name);
            updateInputElement("note-email", data.email);
            updateInputElement("note-tel", data.tel);
            updateInputElement("note-url", data.url);
            updateInputElement("note-textarea", data.textarea);
        })
        .catch(error => {
            console.error("Error:", error);
            // Display error message to the user
            document.getElementById("statusMessage").innerHTML = "<p class='errorMessage'>An error occurred. Please try again later.</p>";
        });
    });

    // Function to update input element and its parent based on data value
    function updateInputElement(id, value) {
        var element = document.getElementById(id);

        // Replace asterisks with proper opening and closing <u> tags
        value = value.replace(/\*!(.*?)!\*/g, '<u>$1</u>'); // New format
    
        element.innerHTML = value;
    
        // Toggle "red" class based on whether the value is empty or not
        element.parentElement.classList.toggle("red", value);
    }
});


jQuery(document).ready(function($) {

   
});

/*document.addEventListener("DOMContentLoaded", function() {
    document.getElementById("frmContact").addEventListener("submit", function(event) {
        event.preventDefault(); // Prevent the form from submitting normally

        // Your AJAX logic goes here
        var formData = new FormData(this);
        formData.append('action', 'maspik_handle_playground_form'); // Add action to formData

        fetch(ajax_object.ajax_url, {
            method: "POST",
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById("statusMessage").innerHTML = "<p class='" + data.status + "Message'>" + data.message + "</p>";
            // Add innerHTML and class based on data value for each input element
            var noteNameElement = document.getElementById("note-name");
            noteNameElement.innerHTML = data.name;
            noteNameElement.parentElement.classList.toggle("red", data.name);
            
            var noteEmailElement = document.getElementById("note-email");
            noteEmailElement.innerHTML = data.email;
            noteEmailElement.parentElement.classList.toggle("red", data.email);
            
            var noteTelElement = document.getElementById("note-tel");
            noteTelElement.innerHTML = data.tel;
            noteTelElement.parentElement.classList.toggle("red", data.tel);
            
            var noteTextareaElement = document.getElementById("note-textarea");
            noteTextareaElement.innerHTML = data.textarea;
            noteTextareaElement.parentElement.classList.toggle("red", data.textarea);
        })
        .catch(error => {
            console.error("Error:", error);
            document.getElementById("statusMessage").innerHTML = "<p class='errorMessage'>An error occurred. Please try again later.</p>";
        });
    });
});
*/