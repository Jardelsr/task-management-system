<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>API Documentation</title>
    <link rel="stylesheet" type="text/css" href="{{ url('swagger-ui/swagger-ui.css') }}" />
    <style>
        html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
        *, *:before, *:after { box-sizing: inherit; }
        body { margin:0; background: #fafafa; }
        .swagger-ui .topbar { display: none; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    
    <script src="{{ url('swagger-ui/swagger-ui-bundle.js') }}"></script>
    <script src="{{ url('swagger-ui/swagger-ui-standalone-preset.js') }}"></script>
    <script>
        window.onload = function() {
            console.log('Initializing Swagger UI...');
            
            // Embed the spec directly
            const spec = {!! json_encode($openApiSpec) !!};
            console.log('Loaded spec with', Object.keys(spec.paths || {}).length, 'paths');
            
            try {
                const ui = SwaggerUIBundle({
                    spec: spec,
                    dom_id: '#swagger-ui',
                    deepLinking: true,
                    presets: [
                        SwaggerUIBundle.presets.apis,
                        SwaggerUIStandalonePreset
                    ],
                    plugins: [
                        SwaggerUIBundle.plugins.DownloadUrl
                    ],
                    layout: "StandaloneLayout",
                    onComplete: function() {
                        console.log('Swagger UI loaded successfully!');
                    }
                });
                
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('swagger-ui').innerHTML = '<div style="padding: 20px; color: red;">Error: ' + error.message + '</div>';
            }
        };
    </script>
</body>
</html>