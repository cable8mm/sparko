import json
import urllib.error
import urllib.parse
import urllib.request
import uuid
from pathlib import Path

ENV = {}
for line in Path('.env').read_text().splitlines():
    if '=' in line and not line.strip().startswith('#'):
        key, _, value = line.partition('=')
        ENV[key.strip()] = value.strip().strip('"')

secret = ENV.get('PORTONE_API_SECRET', '')
channel = ENV.get('PORTONE_E2E_CHANNEL_KEY', '')
base = ENV.get('PORTONE_BASE_URL', 'https://api.portone.io').rstrip('/')
headers = {'Authorization': 'PortOne ' + secret, 'Content-Type': 'application/json'}


def request(method, path, body=None):
    payload = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(base + path, data=payload, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=30) as response:
            return response.status, json.loads(response.read().decode())
    except urllib.error.HTTPError as error:
        return error.code, json.loads(error.read().decode() or b'{}')


customer = 'sparko-webhook-' + uuid.uuid4().hex[:10]
status, issued = request('POST', '/billing-keys', {
    'channelKey': channel,
    'customer': {'id': customer, 'name': {'full': '포트원'}, 'phoneNumber': '010-0000-1234', 'email': 'test@portone.io'},
    'method': {'card': {'credential': {
        'number': '4673090000000032', 'expiryYear': '30', 'expiryMonth': '12',
        'birthOrBusinessRegistrationNumber': '900101', 'passwordTwoDigits': '00',
    }}},
})
if status not in (200, 201):
    raise SystemExit(f'issue failed {status} {issued}')
key = issued['billingKeyInfo']['billingKey']

payment_id = 'sparko-webhook-' + uuid.uuid4().hex[:16]
charge_status, charge = request('POST', '/payments/' + payment_id + '/billing-key', {
    'billingKey': key,
    'orderName': 'Webhook observation',
    'amount': {'total': 1000},
    'currency': 'KRW',
})

lookup_status, lookup = request('GET', '/payments/' + payment_id)
result = {
    'charge_http_status': charge_status,
    'charge_error': charge.get('code'),
    'lookup_http_status': lookup_status,
    'payment_id': payment_id,
    'status': lookup.get('status'),
    'amount_total': (lookup.get('amount') or {}).get('total'),
    'store_id': lookup.get('storeId'),
    'has_transaction_id': bool(lookup.get('transactionId')),
    'lookup_keys': sorted(lookup.keys()),
}
print(json.dumps(result, indent=2, ensure_ascii=False))

request('DELETE', '/billing-keys/' + urllib.parse.quote(key))
