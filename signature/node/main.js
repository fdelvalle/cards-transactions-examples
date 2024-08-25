const http = require("http");
const crypto = require("crypto");

const hostname = "127.0.0.1";
const port = 1080;

const server = http.createServer((req, res) => {
    let body = '';

    req.on('data', chunk => {
        body += chunk.toString();
    });

    req.on('end', async () => {
        try {
            const bodyObject = JSON.parse(body);

            if (req.url === "/transactions/authorizations" || req.url.startsWith("/transactions/adjustments")) {
                await handleTransaction(bodyObject, req, res);
            } else {
                res.statusCode = 404;
                res.end();
            }
        } catch (error) {
            console.error("Error parsing JSON body:", error);
            res.statusCode = 400;
            res.end("Invalid JSON");
        }
    });
});

server.listen(port, hostname, () => {
    console.log(`Server running at http://${hostname}:${port}/`);
});

const handleTransaction = async (bodyObject, req, res) => {
    let response = {
        Status: 'APPROVED',
        StatusDetail: 'APPROVED',
        Message: 'OK'
    };

    if (!(await checkSignature(req, bodyObject))) {
        console.log("Invalid signature, aborting");
        response.StatusDetail = response.Status = 'DENIED';
        response.Message = `Signature mismatch. Received ${req.headers['x-signature']}, calculated ${await calculateSignature(req, bodyObject)}`;
    } else {
        console.log("Transaction processed");
    }

    let responseBody = Buffer.from(JSON.stringify(response));
    await signResponse(responseBody, req, res);
    res.setHeader("Content-Type", "application/json");
    res.end(responseBody);
};

const checkSignature = async (req, bodyObject) => {
    const receivedSignature = req.headers["x-signature"].replace("hmac-sha256 ", "");
    const calculatedSignature = await calculateSignature(req, bodyObject);

    const receivedBytes = Buffer.from(receivedSignature, "base64");
    const calculatedBytes = Buffer.from(calculatedSignature, "base64");

    const signaturesMatch = crypto.timingSafeEqual(receivedBytes, calculatedBytes);

    if (!signaturesMatch) {
        console.log(`Signature mismatch. Received ${receivedSignature}, calculated ${calculatedSignature}`);
    } else {
        console.log(`Signature Validates. Received ${receivedSignature}, calculated ${calculatedSignature}`);
    }

    return signaturesMatch;
};

const calculateSignature = async (req, bodyObject) => {
    const endpoint = req.headers["x-endpoint"];
    const timestamp = req.headers["x-timestamp"];
    const secret = getApiSecret(req.headers["x-api-key"]);
    console.log(timestamp);
    console.log(secret);
    console.log(endpoint);
    console.log(JSON.stringify(bodyObject));
    const hmac = crypto.createHmac("sha256", secret)
        .update(timestamp)
        .update(endpoint)
        .update(JSON.stringify(bodyObject));

    return hmac.digest("base64");
};

const signResponse = async (body, req, res) => {
    const endpoint = req.headers["x-endpoint"];
    const timestamp = Math.floor(Date.now() / 1000).toString();
    const secret = getApiSecret(req.headers["x-api-key"]);

    const hmac = crypto.createHmac("sha256", secret)
        .update(timestamp)
        .update(endpoint);

    if (body) {
        hmac.update(body);
    }

    const signature = hmac.digest("base64");

    res.setHeader("X-Endpoint", endpoint);
    res.setHeader("X-Timestamp", timestamp);
    res.setHeader("X-Signature", "hmac-sha256 " + signature);
};

const getApiSecret = (apiKey) => {
    return Buffer.from(apiSecrets[apiKey], "base64");
};

var apiSecrets = {
    "{{api-key}}": "{{secret-key}}",
};
