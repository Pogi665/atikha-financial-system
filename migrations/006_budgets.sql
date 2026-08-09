-- Monthly expense budgets per category for Executive Suite utilization KPIs.
-- Admin CRUD UI is deferred; this migration seeds defaults from trailing spend.

CREATE TABLE IF NOT EXISTS Budgets (
  BudgetID   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  Category   VARCHAR(100) NOT NULL,
  Year       SMALLINT UNSIGNED NOT NULL,
  Month      TINYINT UNSIGNED NOT NULL,
  Amount     DECIMAL(10, 2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_budget_category_period (Category, Year, Month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO Budgets (Category, Year, Month, Amount)
SELECT
  cat.Name,
  YEAR(CURDATE()),
  months.n,
  COALESCE(ROUND(avg_data.avg_amount * 1.05, 2), 10000.00)
FROM Categories cat
CROSS JOIN (
  SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
  UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
  UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
) AS months
LEFT JOIN (
  SELECT Category, AVG(monthly_total) AS avg_amount
  FROM (
    SELECT Category, SUM(Amount) AS monthly_total
    FROM Expenses
    WHERE Date_Incurred >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 3 MONTH)
      AND Date_Incurred < DATE_FORMAT(CURDATE(), '%Y-%m-01')
    GROUP BY Category, DATE_FORMAT(Date_Incurred, '%Y-%m')
  ) AS monthly_sums
  GROUP BY Category
) AS avg_data ON avg_data.Category = cat.Name
WHERE cat.Type = 'Expense'
  AND cat.Is_Active = 1
ON DUPLICATE KEY UPDATE Amount = VALUES(Amount);
