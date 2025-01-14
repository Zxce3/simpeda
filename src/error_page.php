<?php

function displayErrorPage($errorMessage = 'An unexpected error has occurred.')
{
    
    error_log($errorMessage);

    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - Something Went Wrong</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #e0e0e0; /* Light background for neumorphism */
                color: #333; /* Dark text for contrast */
                text-align: center;
                padding: 50px;
            }
            h1 {
                font-size: 2.5em;
            }
            p {
                font-size: 1.2em;
            }
            a {
                color: #007bff; /* Bootstrap primary color */
                text-decoration: none;
            }
            a:hover {
                text-decoration: underline;
            }
            .error-container {
                border-radius: 20px; /* Rounded corners */
                background: #f0f0f0; /* Light background for the container */
                box-shadow: 8px 8px 15px rgba(0, 0, 0, 0.2), 
                            -8px -8px 15px rgba(255, 255, 255, 0.7); /* Neumorphic shadow */
                padding: 40px;
                display: inline-block;
                margin: auto;
                max-width: 600px; /* Limit the width of the container */
            }
        </style>
    </head>

    <body>
        <div class="error-container">
            <h1>Uh-oh!</h1>
            <p><?php echo htmlspecialchars($errorMessage); ?></p>
            <p>Please try again later or return to the <a href="/">homepage</a>.</p>
        </div>
    </body>

    </html>
    <?php
    exit; 
}

?>