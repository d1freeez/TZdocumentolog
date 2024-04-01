SELECT emp.name AS employee, emp.salary AS salary, 
	   boss.name AS chief, boss.salary AS bossSalary 
FROM employees emp 
JOIN employees boss ON emp.chiefId = boss.employeeId 
WHERE emp.salary > boss.salary;