SELECT department,COUNT(employeeid) as quan from employees
GROUP by department
HAVING quan<3