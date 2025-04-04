# SQL to MongoDB Query Converter

**A powerful PHP tool that converts SQL queries to MongoDB syntax**

This repository contains an advanced PHP class that translates SQL statements (SELECT, INSERT, UPDATE, DELETE) into MongoDB query syntax, supporting:

✨ **Full CRUD operations conversion**  
✅ SELECT → find()/aggregate()  
✅ INSERT → insertOne()/insertMany()  
✅ UPDATE → updateMany() with $set  
✅ DELETE → deleteMany()

**Key Features:**
- Supports complex WHERE conditions with logical operators (AND, OR, NOT)
- Converts SQL functions (COUNT, AVG) to MongoDB aggregation operators
- Processes GROUP BY with HAVING clauses
- Maintains ORDER BY, LIMIT functionality
- Type conversion for values (strings, numbers, booleans, null)
- Comprehensive error handling

**Use Cases:**
- Migrating from SQL to MongoDB databases
- Learning MongoDB syntax by comparing with familiar SQL
- Building polyglot persistence applications
- Educational purposes for database courses

**Example Conversion:**
```sql
SELECT produto, preco FROM itens WHERE preco > 100 ORDER BY preco DESC
```

Converts to:
```javascript
db.itens.find({
    "preco": {
        "$gt": "100"
    }
}).projection({
    "produto": 1,
    "preco": 1
}).sort({
    "preco": -1
})
```

**Getting Started:**
1. Include the converter class in your project
2. Call `MongoSQLConverter::convert(your_sql_query)`
3. Get perfect MongoDB syntax

**Contributions Welcome!**  
This project is open for improvements and additional SQL feature support.

---
"If this project has been helpful to you in any way, how about helping me spread the word on Reddit and other social media platforms? I believe we are better together."😊
