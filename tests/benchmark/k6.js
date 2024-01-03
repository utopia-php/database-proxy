import http from 'k6/http';
import { check, sleep } from 'k6';

export default function () {
    const res = http.get('http://localhost:8088/v1/ping', {
        headers: {
            'x-utopia-secret': 'proxy-secret-key',
            'x-utopia-namespace': 'utopia',
            'x-utopia-default-database': 'appwrite'
        }
    });
    check(res, { 'status was 200': (r) => r.status == 200 });
}