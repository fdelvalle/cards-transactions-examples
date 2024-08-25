## Start Flask Server
python main.py

## Change API_KEY and SECRET_KEY

## Execute Curl to test
curl --location 'localhost:1080/transactions/authorizations' \
--header 'x-idempotency-key: 80876dcc-3a31-4b38-bce7-c2f56d60d16b' \
--header 'X-Api-Key: {{api-key}}=' \
--header 'X-Signature: hmac-sha256 Jc6rrYLBP1vmbNwLiUeuX0rNO54+GHtx2pRxRVKZjrk=' \
--header 'X-Timestamp: 1724260844' \
--header 'X-Endpoint: /v3/api/credit-cards/identity/v1/sessions' \
--header 'Content-Type: application/json' \
--data '{
    "event_id": "identity-session-status-changed",
    "session": {
        "id": "iss-2kyAGSHUap0lnoejehdYvqcH8zQ",
        "status": "VERIFIED"
    },
    "idempotency_key": "2kyeY3v5Q9m8GzGD6dfYgrevMr6"
}'