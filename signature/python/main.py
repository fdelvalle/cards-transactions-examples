from flask import Flask, request, jsonify, make_response
import hmac
import hashlib
import base64
import time
import json
from collections import OrderedDict

app = Flask(__name__)

api_secrets = {
   "{{api-key}}": "{{secret-key}}",
}

def get_api_secret(api_key):
    return base64.b64decode(api_secrets.get(api_key, ''))

def calculate_signature(endpoint, timestamp, body, secret):
    #print(body.encode('utf-8'))
    body = json.dumps(json.loads(body), separators=(',', ':'))
    print(timestamp.encode('utf-8') )
    print(secret)
    print(endpoint.encode('utf-8') )
    print(body.encode('utf-8') )
    hmac_obj = hmac.new(secret, msg=timestamp.encode('utf-8'), digestmod=hashlib.sha256)
    hmac_obj.update(endpoint.encode('utf-8'))
    hmac_obj.update(body.encode('utf-8'))
    return base64.b64encode(hmac_obj.digest()).decode('utf-8')

def check_signature(req):
    endpoint = req.headers.get("X-Endpoint")
    timestamp = req.headers.get("X-Timestamp")
    received_signature = req.headers.get("X-Signature", "").replace("hmac-sha256 ", "")
    secret = get_api_secret(req.headers.get("X-Api-Key"))

    calculated_signature = calculate_signature(endpoint, timestamp, req.get_data(as_text=True), secret)

    if not hmac.compare_digest(received_signature, calculated_signature):
        print(f"Signature mismatch. Received {received_signature}, calculated {calculated_signature}")
        return False, calculated_signature

    print(f"Signature Validates. Received {received_signature}, calculated {calculated_signature}")
    return True, calculated_signature

def sign_response(req, body, secret):
    endpoint = req.headers.get("X-Endpoint")
    timestamp = str(int(time.time()))
    print(timestamp.encode('utf-8') )
    print(secret)
    print(endpoint.encode('utf-8') )
    print(body.encode('utf-8') )

    hmac_obj = hmac.new(secret, msg=timestamp.encode('utf-8'), digestmod=hashlib.sha256)
    hmac_obj.update(endpoint.encode('utf-8'))

    if body:
        hmac_obj.update(body.encode('utf-8'))

    signature = base64.b64encode(hmac_obj.digest()).decode('utf-8')

    return timestamp, f"hmac-sha256 {signature}"

@app.route('/transactions/authorizations', methods=['POST'])
@app.route('/transactions/adjustments', methods=['POST'])
def handle_transaction():
    signature_valid, calculated_signature = check_signature(request)

    response_data = OrderedDict({
        'Status': 'APPROVED',
        'StatusDetail': 'APPROVED',
        'Message': 'OK'
    })

    if not signature_valid:
        message = f'Signature mismatch. Received {request.headers.get("X-Signature")}, calculated {calculated_signature}'
        response_data.update(Status='DENIED', StatusDetail='DENIED', Message=message)

    print(response_data)
    #response_json = jsonify(response_data)
    response_json = json.dumps(response_data, separators=(',', ':'))
    print(response_json)
    secret = get_api_secret(request.headers.get("X-Api-Key"))
    timestamp, signature = sign_response(request, response_json, secret)

    response = make_response(response_json)
    response.headers['Content-Type'] = 'application/json'
    response.headers['X-Endpoint'] = request.headers.get("X-Endpoint")
    response.headers['X-Timestamp'] = timestamp
    response.headers['X-Signature'] = signature

    return response

if __name__ == '__main__':
    app.run(host='127.0.0.1', port=1080)
