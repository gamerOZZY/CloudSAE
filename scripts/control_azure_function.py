"""Control simple para Azure Function App usando Azure CLI.

Uso:
  python scripts/control_azure_function.py --app <APP_NAME> --resource-group <RG> --action stop
  python scripts/control_azure_function.py --app <APP_NAME> --resource-group <RG> --action start
  python scripts/control_azure_function.py --app <APP_NAME> --resource-group <RG> --action disable --function <FUNCTION_NAME>
  python scripts/control_azure_function.py --app <APP_NAME> --resource-group <RG> --action enable --function <FUNCTION_NAME>

El script requiere que la Azure CLI (`az`) esté instalada y que el usuario esté autenticado.
También puede recibir `--subscription <SUBSCRIPTION_ID_OR_NAME>` para cambiar el contexto.

Notas:
- `stop` / `start` actúan sobre la Function App completa.
- `disable` / `enable` actúan sobre una función concreta dentro de la Function App mediante el App Setting
  `AzureWebJobs.<FunctionName>.Disabled=true|false`.

El script imprime la cuenta/ suscripción activa antes de ejecutar la acción.
"""

import argparse
import json
import subprocess
import sys


def run(cmd, capture=False):
    try:
        if capture:
            p = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True, text=True)
            return p.stdout.strip()
        else:
            subprocess.run(cmd, check=True)
            return None
    except FileNotFoundError:
        print("Azure CLI (az) no encontrada. Instala Azure CLI antes de ejecutar este script.")
        sys.exit(2)
    except subprocess.CalledProcessError as e:
        print(f"Comando falló: {' '.join(cmd)}")
        if e.stdout:
            print('stdout:', e.stdout)
        if e.stderr:
            print('stderr:', e.stderr)
        sys.exit(e.returncode)


def show_account():
    out = run(["az", "account", "show", "--output", "json"], capture=True)
    try:
        info = json.loads(out)
        print("Cuenta activa:")
        print(f"  Name: {info.get('name')}")
        print(f"  User: {info.get('user', {}).get('name')}")
        print(f"  Id: {info.get('id')}")
        print()
    except Exception:
        print("No se pudo parsear la salida de 'az account show'. Salida cruda:")
        print(out)


def set_subscription(subscription):
    print(f"Cambiando suscripción a: {subscription}")
    run(["az", "account", "set", "--subscription", subscription])


def stop_app(app, rg):
    print(f"Deteniendo Function App '{app}' en resource group '{rg}'...")
    run(["az", "functionapp", "stop", "--name", app, "--resource-group", rg])
    print("Function App detenida.")


def start_app(app, rg):
    print(f"Iniciando Function App '{app}' en resource group '{rg}'...")
    run(["az", "functionapp", "start", "--name", app, "--resource-group", rg])
    print("Function App iniciada.")


def set_function_disabled(app, rg, function_name, disabled=True):
    key = f"AzureWebJobs.{function_name}.Disabled"
    val = "true" if disabled else "false"
    print(f"Estableciendo App Setting {key}={val} en '{app}' ({rg})...")
    run(["az", "functionapp", "config", "appsettings", "set", "--name", app, "--resource-group", rg, "--settings", f"{key}={val}"])
    print("App Setting actualizado.")


def parse_args():
    p = argparse.ArgumentParser(description="Controla una Azure Function App (stop/start/disable/enable)")
    p.add_argument("--app", "-a", help="Nombre de la Function App", required=True)
    p.add_argument("--resource-group", "-g", help="Resource Group donde está la Function App", required=True)
    p.add_argument("--action", "-x", choices=["stop", "start", "disable", "enable"], required=True,
                   help="Acción a ejecutar")
    p.add_argument("--function", "-f", help="Nombre de la función (requerido para disable/enable)")
    p.add_argument("--subscription", "-s", help="ID o nombre de la suscripción (opcional)")
    p.add_argument("--yes", "-y", action="store_true", help="No pedir confirmación")
    return p.parse_args()


def main():
    args = parse_args()

    # check az availability and show account
    try:
        run(["az", "--version"], capture=False)
    except SystemExit:
        # run() already printed error
        raise

    if args.subscription:
        set_subscription(args.subscription)

    show_account()

    if not args.yes:
        ok = input(f"Confirmar acción '{args.action}' sobre app '{args.app}' (y/n): ").strip().lower()
        if ok not in ("y", "s", "yes", "si"):
            print("Cancelado por usuario.")
            sys.exit(0)

    if args.action == "stop":
        stop_app(args.app, args.resource_group)
    elif args.action == "start":
        start_app(args.app, args.resource_group)
    elif args.action in ("disable", "enable"):
        if not args.function:
            print("Para 'disable'/'enable' debes pasar --function <FUNCTION_NAME>")
            sys.exit(2)
        set_function_disabled(args.app, args.resource_group, args.function, disabled=(args.action == "disable"))
    else:
        print("Acción desconocida")


if __name__ == '__main__':
    main()
