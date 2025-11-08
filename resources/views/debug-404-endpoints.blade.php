<!DOCTYPE html>
<html>
<head>
    <title>Debug Custom Endpoint 404</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">Debug Custom Endpoint 404 Issues</h1>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Test Known Good Endpoints -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Test Known Good BPJS Endpoints</h2>
                <div class="space-y-3">
                    <button onclick="testEndpoint('Timestamp', 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/timestamp')" 
                            class="w-full px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                        Test Timestamp (Should Work)
                    </button>
                    <button onclick="testEndpoint('Diagnosa', 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/referensi/diagnosa/A00')" 
                            class="w-full px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                        Test Diagnosa (Should Work)
                    </button>
                    <button onclick="testEndpoint('Faskes', 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/referensi/faskes/1702R002/2')" 
                            class="w-full px-4 py-2 bg-purple-500 text-white rounded hover:bg-purple-600">
                        Test Faskes (Should Work)
                    </button>
                </div>
            </div>

            <!-- Test Potentially Problematic Endpoints -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Test Problematic Endpoints</h2>
                <div class="space-y-3">
                    <button onclick="testEndpoint('Dokter DPJP (Complex)', 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/referensi/dokter/pelayanan/1/tglPelayanan/2025-07-31/Spesialis/INT')" 
                            class="w-full px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600">
                        Test Dokter DPJP (Complex URL)
                    </button>
                    <button onclick="testEndpoint('Procedure', 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/referensi/procedure/001')" 
                            class="w-full px-4 py-2 bg-orange-500 text-white rounded hover:bg-orange-600">
                        Test Procedure
                    </button>
                    <button onclick="testEndpoint('Dokter', 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/referensi/dokter/266154')" 
                            class="w-full px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                        Test Dokter (Specific ID)
                    </button>
                </div>
            </div>
        </div>

        <!-- Custom Test Form -->
        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <h2 class="text-xl font-semibold mb-4">Test Custom URL</h2>
            <div class="flex gap-3">
                <input id="customUrl" type="url" placeholder="https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/..." 
                       class="flex-1 px-3 py-2 border border-gray-300 rounded-md">
                <button onclick="testCustomUrl()" 
                        class="px-6 py-2 bg-indigo-500 text-white rounded hover:bg-indigo-600">
                    Test URL
                </button>
            </div>
            <p class="text-sm text-gray-500 mt-2">
                💡 Masukkan URL BPJS lengkap untuk testing
            </p>
        </div>

        <!-- Results -->
        <div id="results" class="bg-white rounded-lg shadow p-6 mt-6" style="display: none;">
            <h2 class="text-xl font-semibold mb-4">Test Results</h2>
            <div id="resultContent" class="space-y-3"></div>
        </div>

        <!-- LocalStorage Custom Endpoints -->
        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <h2 class="text-xl font-semibold mb-4">LocalStorage Custom Endpoints</h2>
            <div class="flex gap-3 mb-4">
                <button onclick="loadCustomEndpoints()" 
                        class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                    Load from LocalStorage
                </button>
                <button onclick="clearCustomEndpoints()" 
                        class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                    Clear LocalStorage
                </button>
            </div>
            <div id="customEndpoints" class="space-y-2"></div>
        </div>

        <!-- Route Test -->
        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <h2 class="text-xl font-semibold mb-4">Route Availability Test</h2>
            <div class="space-y-3">
                <button onclick="testRoute('/bpjs-monitoring/data')" 
                        class="w-full px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 text-left">
                    Test Main Data Route: /bpjs-monitoring/data
                </button>
                <button onclick="testRoute('/bpjs-monitoring/test-custom-endpoint')" 
                        class="w-full px-4 py-2 bg-purple-500 text-white rounded hover:bg-purple-600 text-left">
                    Test Custom Endpoint Route: /bpjs-monitoring/test-custom-endpoint
                </button>
            </div>
        </div>
    </div>

    <script>
        function addResult(name, result) {
            const resultsDiv = document.getElementById('results');
            const contentDiv = document.getElementById('resultContent');
            
            resultsDiv.style.display = 'block';
            
            const resultElement = document.createElement('div');
            resultElement.className = 'p-4 border rounded-lg ' + 
                (result.status === 'success' ? 'bg-green-50 border-green-200' : 
                 result.status === 'timeout' ? 'bg-yellow-50 border-yellow-200' : 
                 'bg-red-50 border-red-200');
            
            resultElement.innerHTML = `
                <div class="flex justify-between items-start mb-2">
                    <h4 class="font-semibold">${name}</h4>
                    <span class="px-2 py-1 text-xs rounded ${
                        result.status === 'success' ? 'bg-green-100 text-green-800' :
                        result.status === 'timeout' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-red-100 text-red-800'
                    }">${result.status}</span>
                </div>
                <div class="text-sm space-y-1">
                    <div><strong>Response Time:</strong> ${result.response_time}ms</div>
                    <div><strong>Status Code:</strong> ${result.code}</div>
                    <div><strong>Message:</strong> ${result.message}</div>
                    ${result.is_bpjs ? '<div class="text-blue-600"><strong>BPJS Auth:</strong> ✅ Used</div>' : ''}
                    ${result.error ? `<div class="text-red-600"><strong>Error:</strong> ${result.error}</div>` : ''}
                </div>
            `;
            
            contentDiv.appendChild(resultElement);
        }

        async function testEndpoint(name, url) {
            addResult(name, { status: 'testing', message: 'Testing...', response_time: 0, code: '...' });
            
            try {
                const response = await fetch('/bpjs-monitoring/test-custom-endpoint', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        url: url,
                        method: 'GET',
                        timeout: 15
                    })
                });

                const result = await response.json();
                
                // Remove the testing result and add real result
                const contentDiv = document.getElementById('resultContent');
                contentDiv.removeChild(contentDiv.lastChild);
                
                addResult(name, result);
                
            } catch (error) {
                // Remove the testing result and add error result
                const contentDiv = document.getElementById('resultContent');
                contentDiv.removeChild(contentDiv.lastChild);
                
                addResult(name, {
                    status: 'error',
                    message: error.message,
                    response_time: 0,
                    code: 'ERROR',
                    error: error.toString()
                });
            }
        }

        function testCustomUrl() {
            const url = document.getElementById('customUrl').value;
            if (!url) {
                alert('Please enter a URL');
                return;
            }
            testEndpoint('Custom URL', url);
        }

        async function testRoute(route) {
            try {
                const response = await fetch(route, { method: 'GET' });
                const isJson = response.headers.get('content-type')?.includes('application/json');
                
                let result = {
                    status: response.ok ? 'success' : 'error',
                    response_time: 0,
                    code: response.status,
                    message: response.statusText,
                    content_type: response.headers.get('content-type')
                };

                if (isJson && response.ok) {
                    const data = await response.json();
                    result.message = `JSON Response OK (${Object.keys(data).length} keys)`;
                }
                
                addResult(`Route: ${route}`, result);
                
            } catch (error) {
                addResult(`Route: ${route}`, {
                    status: 'error',
                    message: error.message,
                    response_time: 0,
                    code: 'ERROR',
                    error: error.toString()
                });
            }
        }

        function loadCustomEndpoints() {
            const stored = localStorage.getItem('bpjs_custom_endpoints');
            const customEndpointsDiv = document.getElementById('customEndpoints');
            
            if (stored) {
                try {
                    const endpoints = JSON.parse(stored);
                    customEndpointsDiv.innerHTML = '';
                    
                    if (endpoints.length === 0) {
                        customEndpointsDiv.innerHTML = '<p class="text-gray-500">No custom endpoints in localStorage</p>';
                        return;
                    }
                    
                    endpoints.forEach((endpoint, index) => {
                        const endpointDiv = document.createElement('div');
                        endpointDiv.className = 'p-3 border rounded-lg bg-gray-50';
                        endpointDiv.innerHTML = `
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="font-semibold">${endpoint.name}</h4>
                                    <p class="text-sm text-gray-600">${endpoint.url}</p>
                                    <p class="text-xs text-gray-500">${endpoint.description}</p>
                                </div>
                                <div class="space-x-2">
                                    <button onclick="testEndpoint('${endpoint.name}', '${endpoint.url}')" 
                                            class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600">
                                        Test
                                    </button>
                                    <button onclick="deleteCustomEndpoint(${index})" 
                                            class="px-3 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        `;
                        customEndpointsDiv.appendChild(endpointDiv);
                    });
                    
                } catch (e) {
                    customEndpointsDiv.innerHTML = '<p class="text-red-500">Error parsing localStorage data</p>';
                }
            } else {
                customEndpointsDiv.innerHTML = '<p class="text-gray-500">No custom endpoints in localStorage</p>';
            }
        }

        function clearCustomEndpoints() {
            if (confirm('Are you sure you want to clear all custom endpoints?')) {
                localStorage.removeItem('bpjs_custom_endpoints');
                loadCustomEndpoints();
            }
        }

        function deleteCustomEndpoint(index) {
            const stored = localStorage.getItem('bpjs_custom_endpoints');
            if (stored) {
                try {
                    const endpoints = JSON.parse(stored);
                    endpoints.splice(index, 1);
                    localStorage.setItem('bpjs_custom_endpoints', JSON.stringify(endpoints));
                    loadCustomEndpoints();
                } catch (e) {
                    alert('Error deleting endpoint');
                }
            }
        }

        // Load custom endpoints on page load
        window.onload = function() {
            loadCustomEndpoints();
        };
    </script>
</body>
</html>
