// k6 load test for a STAGING copy of e-RESERVE:  k6 run --vus 300 --duration 2m deploy/loadtest.js
// Set the target:  k6 run -e BASE=https://staging.example.com ...
import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE = __ENV.BASE || 'http://localhost/e-RESERVE/public';

export const options = {
  thresholds: {
    http_req_failed: ['rate<0.01'],      // <1% errors
    http_req_duration: ['p(95)<1500'],   // 95% of requests under 1.5 s
  },
};

export default function () {
  const landing = http.get(`${BASE}/`);
  check(landing, { 'landing 200': (r) => r.status === 200 });
  sleep(1);

  const login = http.get(`${BASE}/login`);
  check(login, { 'login 200': (r) => r.status === 200 });
  sleep(1);

  const register = http.get(`${BASE}/register`);
  check(register, { 'register 200': (r) => r.status === 200 });
  sleep(2);
}
