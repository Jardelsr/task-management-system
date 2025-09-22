<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Task Management System API Documentation' }}</title>
    <link rel="stylesheet" type="text/css" href="{{ url('swagger-ui/swagger-ui.css') }}" />
    <link rel="icon" type="image/png" href="https://unpkg.com/swagger-ui-dist@3.52.5/favicon-32x32.png" sizes="32x32" />
    <style>
        html {
            box-sizing: border-box;
            overflow: -moz-scrollbars-vertical;
            overflow-y: scroll;
        }
        *, *:before, *:after {
            box-sizing: inherit;
        }
        body {
            margin:0;
            background: #fafafa;
        }
        .swagger-ui .topbar {
            background-color: #2c3e50;
        }
        .api-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .api-header h1 {
            margin: 0;
            font-size: 2em;
            font-weight: 300;
        }
        .api-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        .loading {
            text-align: center;
            padding: 50px;
            font-size: 18px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="api-header">
        <h1>Task Management System API</h1>
        <p>Interactive API Documentation - Environment: {{ $environment ?? 'production' }}</p>
    </div>

    <div id="swagger-ui">
        <div class="loading">🔄 Loading API Documentation...</div>
    </div>

    <script src="{{ url('swagger-ui/swagger-ui-bundle.js') }}"></script>
    <script src="{{ url('swagger-ui/swagger-ui-standalone-preset.js') }}"></script>
    <script>
        window.onload = function() {
            console.log('Initializing Swagger UI...');
            
            const specUrl = '{{ $specUrl ?? request()->getSchemeAndHttpHost() . "/api/v1/openapi.json" }}';
            console.log('Loading OpenAPI spec from:', specUrl);
            
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
                    validatorUrl: null,
                    docExpansion: "list",
                    operationsSorter: "alpha",
                    tagsSorter: "alpha",
                    filter: true,
                    supportedSubmitMethods: ['get', 'post', 'put', 'delete', 'patch'],
                    onComplete: function() {
                        console.log('Swagger UI loaded successfully');
                        const spec = window.ui.specSelectors.spec().toJS();
                        const pathCount = spec.paths ? Object.keys(spec.paths).length : 0;
                        console.log('Number of API endpoints loaded:', pathCount);
                        
                        if (pathCount === 0) {
                            console.warn('No API endpoints found in specification');
                        }
                    },
                    onFailure: function(data) {
                        console.error('Failed to load API spec:', data);
                        document.getElementById('swagger-ui').innerHTML = `
                            <div style="padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24; margin: 20px;">
                                <h3>⚠️ Failed to Load API Documentation</h3>
                                <p><strong>Error:</strong> Unable to load OpenAPI specification</p>
                                <p><strong>Spec URL:</strong> <a href="${specUrl}" target="_blank">${specUrl}</a></p>
                                <p>Please check the browser console for more details.</p>
                                <p><strong>Troubleshooting:</strong></p>
                                <ul>
                                    <li>Verify Docker containers are running</li>
                                    <li>Check if API server is accessible at localhost:8000</li>
                                    <li>Try opening the OpenAPI spec URL directly</li>
                                </ul>
                            </div>
                        `;
                    }
                });

                window.ui = ui;
                
            } catch (error) {
                console.error('Error initializing Swagger UI:', error);
                document.getElementById('swagger-ui').innerHTML = `
                    <div style="padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24; margin: 20px;">
                        <h3>⚠️ Swagger UI Initialization Error</h3>
                        <p><strong>Error:</strong> ${error.message}</p>
                        <p>Please check the browser console for more details.</p>
                    </div>
                `;
            }
        };
    </script>
</body>
</html>