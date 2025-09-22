<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>API Documentation Test</title>
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
        console.log('Scripts loaded');
        console.log('SwaggerUIBundle available:', typeof SwaggerUIBundle !== 'undefined');
        console.log('SwaggerUIStandalonePreset available:', typeof SwaggerUIStandalonePreset !== 'undefined');
        
        window.onload = function() {
            console.log('Window loaded, initializing...');
            
            if (typeof SwaggerUIBundle === 'undefined') {
                document.getElementById('swagger-ui').innerHTML = '<p style="color: red; padding: 20px;">SwaggerUIBundle not loaded</p>';
                return;
            }
            
            if (typeof SwaggerUIStandalonePreset === 'undefined') {
                document.getElementById('swagger-ui').innerHTML = '<p style="color: red; padding: 20px;">SwaggerUIStandalonePreset not loaded</p>';
                return;
            }
            
            const specUrl = '{{ $specUrl ?? request()->getSchemeAndHttpHost() . "/api/v1/openapi.json" }}';
            console.log('Loading spec from:', specUrl);
            
            try {
                const ui = SwaggerUIBundle({
                    url: specUrl,
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
                    },
                    onFailure: function(data) {
                        console.error('Failed to load:', data);
                        document.getElementById('swagger-ui').innerHTML = '<p style="color: red; padding: 20px;">Failed to load API specification</p>';
                    }
                });
                
                console.log('SwaggerUIBundle initialized');
                
            } catch (error) {
                console.error('Initialization error:', error);
                document.getElementById('swagger-ui').innerHTML = '<p style="color: red; padding: 20px;">Error: ' + error.message + '</p>';
            }
        };
    </script>
</body>
</html>