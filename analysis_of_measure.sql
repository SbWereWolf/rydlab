
/* PostgreSql */

/* setup table*/
CREATE TABLE log AS
  SELECT
    round(random() * 5)                  AS device_id,
    now() - random() * INTERVAL '5 days' AS ts,
    (random() * 9) + (random() * 99)     AS value
  FROM generate_series(1, 666);

/* perform analysis of measure data */
WITH mesuare_dates (ts, device, avg_value) AS (
    SELECT
      date_trunc('day', l.ts) ts,
      l.device_id             device,
      AVG(l.value)            avg_value
    FROM
      log l
    GROUP BY
      date_trunc('day', l.ts), l.device_id
)
  , variation_tolerance ( variation ) AS (
    SELECT 0.05
)
  , mesuare_range_ratio (minimum,maximum) AS (
    SELECT
      1 - vt.variation minimum,
      1 + vt.variation maximum
    FROM variation_tolerance vt
)
  , target_dates (ts, device) AS (
    SELECT
      mdc.ts,
      mdc.device
    FROM
      mesuare_range_ratio mrr,
      mesuare_dates mdc
      JOIN mesuare_dates mdp
        ON mdc.device = mdp.device
    WHERE
      mdc.ts = mdp.ts + INTERVAL '1 day'
      AND mdc.avg_value NOT BETWEEN mdp.avg_value * mrr.minimum AND mdp.avg_value * mrr.maximum
)
SELECT
  td.ts     date,
  td.device device,
  l.ts      mesuare_timestamp,
  l.value   mesuare_value
FROM
  target_dates td,
  log l
WHERE
  l.ts >= td.ts
  AND l.ts < td.ts + INTERVAL '1 day'
  AND l.device_id = td.device
ORDER BY l.device_id, td.ts, l.ts;
