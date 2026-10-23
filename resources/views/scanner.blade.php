<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Scanner</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-100 font-sans flex flex-col h-screen"> 
    @include('partials.toast')
    <nav class="bg-indigo-600 p-4 text-white text-center shadow">
        <h1 class="text-2xl font-bold">Ticket Scanner</h1>
    </nav>

    <div class="flex-grow flex flex-col items-center justify-center p-4">
        <div class="bg-white p-4 rounded-lg shadow-lg w-full max-w-md">
            <div id="reader" width="100%"></div>
            
            <div id="result" class="mt-4 p-4 text-center rounded hidden"></div>
        </div>
        <a href="/{{ env('ADMIN_PREFIX', 'eventix-portal-mgmt') }}" class="mt-6 text-indigo-600 hover:underline">Back to Dashboard</a>
    </div>

    <!-- Include the HTML5 QR Code Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script>
        const html5QrcodeScanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: {width: 250, height: 250} }, /* verbose= */ false);
        const resultDiv = document.getElementById('result');
        let isProcessing = false;

        function onScanSuccess(decodedText, decodedResult) {
            if (isProcessing) return;
            isProcessing = true;
            html5QrcodeScanner.pause();

            resultDiv.classList.remove('hidden', 'bg-green-100', 'text-green-800', 'bg-red-100', 'text-red-800', 'bg-yellow-100', 'text-yellow-800');
            resultDiv.classList.add('bg-blue-100', 'text-blue-800', 'block');
            resultDiv.innerText = 'Processing ticket...';

            fetch('/scanner/process', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ ticket_code: decodedText })
            })
            .then(response => response.json())
            .then(data => {
                resultDiv.classList.remove('bg-blue-100', 'text-blue-800');
                if (data.success) {
                    resultDiv.classList.add('bg-green-100', 'text-green-800');
                    resultDiv.innerHTML = '<strong>✅ SUCCESS</strong><br>' + data.message;
                } else {
                    resultDiv.classList.add('bg-red-100', 'text-red-800');
                    resultDiv.innerHTML = '<strong>❌ ERROR</strong><br>' + data.message;
                }
                
                // Resume scanning after 3 seconds
                setTimeout(() => {
                    resultDiv.classList.add('hidden');
                    isProcessing = false;
                    html5QrcodeScanner.resume();
                }, 3000);
            })
            .catch(error => {
                resultDiv.classList.remove('bg-blue-100', 'text-blue-800');
                resultDiv.classList.add('bg-red-100', 'text-red-800');
                resultDiv.innerHTML = '<strong>❌ ERROR</strong><br>Network or server error.';
                setTimeout(() => {
                    resultDiv.classList.add('hidden');
                    isProcessing = false;
                    html5QrcodeScanner.resume();
                }, 3000);
            });
        }

        function onScanFailure(error) {
            // handle scan failure, usually better to ignore and keep scanning.
        }

        html5QrcodeScanner.render(onScanSuccess, onScanFailure);
    </script>
</body>
</html>
