import os
import pymysql


def get_source_connection():

    return pymysql.connect(
        host=os.environ["SOURCE_MYSQL_HOST"],
        user=os.environ["SOURCE_MYSQL_USER"],
        password=os.environ["SOURCE_MYSQL_PASSWORD"],
        database=os.environ["SOURCE_MYSQL_DATABASE"],
        port=int(os.environ.get("SOURCE_MYSQL_PORT", "3306")),
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=False
    )


def get_target_connection():

    return pymysql.connect(
        host=os.environ["TARGET_MYSQL_HOST"],
        user=os.environ["TARGET_MYSQL_USER"],
        password=os.environ["TARGET_MYSQL_PASSWORD"],
        database=os.environ["TARGET_MYSQL_DATABASE"],
        port=int(os.environ.get("TARGET_MYSQL_PORT", "3306")),
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=False
    )