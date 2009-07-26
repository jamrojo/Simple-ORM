# Simple-ORM

Simple class that provides DBMS independent access and management of databases.

Currently it features:

*   Easy iteration and data update inspired by ActiveRecords
*   Built on top of PDO
*   Implicit database connection, connection only when is necesary
*   Native query cache support.
*   Support single-level transactions (limited by PDO)
*   Data filtering with callback functions per column
