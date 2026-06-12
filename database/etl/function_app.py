import azure.functions as func
import logging

from shared.etl_service import run_etl

app = func.FunctionApp()

@app.timer_trigger(
    schedule="0 0 0 */7 * *",
    arg_name="timer",
    run_on_startup=False,
    use_monitor=True
)
def mysql_etl(timer: func.TimerRequest):

    logging.info("Inicio ETL MySQL")

    try:
        run_etl()
        logging.info("ETL finalizado correctamente")

    except Exception as ex:
        logging.exception(f"Error ETL: {str(ex)}")
        raise