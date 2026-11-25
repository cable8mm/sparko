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

if not secret or not channel:
    raise SystemExit('PORTONE_API_SECRET and PORTONE_E2E_CHANNEL_KEY are required.')

headers = {
    'Authorization': f'PortOne {secret}',
    'Content-Type': 'application/json',
}


def request(method, path, body=None):
    payload = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(base + path, data=payload, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=30) as response:
            return response.status, json.loads(response.read().decode())
    except urllib.error.HTTPError as error:
        return error.code, json.loads(error.read().decode() or b'{}')


def redact(value):
    if not isinstance(value, str) or len(value) < 8:
        return 'redacted'
    return value[:4] + '...' + value[-4:]


issue_body = {
    'channelKey': channel,
    'customer': {
        'id': 'sparko-probe-' + uuid.uuid4().hex[:12],
        'name': {'full': '포트원'},
        'phoneNumber': '010-0000-1234',
        'email': 'test@portone.io',
    },
    'method': {
        'card': {
            'credential': {
                'number': '4673090000000032',
                'expiryYear': '30',
                'expiryMonth': '12',
                'birthOrBusinessRegistrationNumber': '900101',
                'passwordTwoDigits': '00',
            }
        }
    },
}

issue_status, issue = request('POST', '/billing-keys', issue_body)
result = {
    'issue_http_status': issue_status,
    'issue_code': issue.get('code'),
    'issue_message': issue.get('message'),
    'issue_response_keys': sorted(issue.keys()),
}

info = issue.get('billingKeyInfo') or {}
if issue_status in (200, 201) and info:
    key = info.get('billingKey')
    result.update({
        'issued_status': info.get('status'),
        'billing_key_info_keys': sorted(info.keys()),
        'billing_key_redacted': redact(key),
        'card_brand': ((info.get('methods') or [{}])[0].get('card') or {}).get('brand'),
        'channel_type': ((info.get('channels') or [{}])[0].get('type')),
    })

    lookup_status, lookup = request('GET', '/billing-keys/' + urllib.parse.quote(str(key)))
    result.update({
        'lookup_http_status': lookup_status,
        'lookup_code': lookup.get('code'),
        'lookup_response_keys': sorted(lookup.keys()),
        'lookup_status_value': lookup.get('status'),
        'lookup_info_keys': sorted((lookup.get('billingKeyInfo') or {}).keys()),
    })

    delete_status, delete = request('DELETE', '/billing-keys/' + urllib.parse.quote(str(key)))
    result.update({
        'delete_http_status': delete_status,
        'delete_code': delete.get('code'),
    })

print(json.dumps(result, indent=2, ensure_ascii=False))
