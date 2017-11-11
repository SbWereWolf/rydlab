
/* PostgreSql */

/* setup table*/
CREATE TABLE log AS
  SELECT
    round(random() * 5)                  AS device_id,
    now() - random() * INTERVAL '5 days' AS ts,
    (random() * 9) + (random() * 99)     AS value
  FROM generate_series(1, 666);

/* perform analysis of measure data */
WITH mesuare_dates (ts, device,avg_value) AS (
    SELECT
      date_trunc('day', l.ts),
      l.device_id,
      AVG(l.value)
    FROM
      log l
    GROUP BY
      date_trunc('day', l.ts),l.device_id
)
  , target_dates (ts,device) AS (
    SELECT mdc.ts,mdc.device

    FROM
      mesuare_dates mdc
      join mesuare_dates mdp
      on mdc.device = mdp.device
    WHERE
      mdc.ts = mdp.ts + INTERVAL '1 day'
      AND mdc.avg_value BETWEEN mdp.avg_value * 0.95 AND mdp.avg_value * 1.05
)
SELECT td.ts,td.device, l.ts,l.value
FROM
  target_dates td,
  log l
WHERE
  l.ts >= td.ts
  AND l.ts < td.ts + INTERVAL '1 day'
  AND l.device_id = td.device
ORDER BY td.ts, l.device_id, l.ts;