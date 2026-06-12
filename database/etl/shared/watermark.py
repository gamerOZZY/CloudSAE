def get_last_watermark(conn):

    with conn.cursor() as cursor:

        cursor.execute("""
            SELECT last_processed
            FROM etl_control
            WHERE process_name='mysql_etl'
        """)

        row = cursor.fetchone()

        return row["last_processed"]


def update_watermark(conn, watermark):

    with conn.cursor() as cursor:

        cursor.execute("""
            UPDATE etl_control
            SET last_processed=%s
            WHERE process_name='mysql_etl'
        """, (watermark,))

    conn.commit()