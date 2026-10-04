"""Management integration entrypoint for the Phase 3 journal fixture.
Bootstrap an explicitly named disposable DB with test_accounting.php first.
The shared browser suite checks both roles, forecasts, reviews and ledger behavior.
"""
import argparse
from pathlib import Path
import re
import subprocess
import sys

parser=argparse.ArgumentParser()
parser.add_argument('--database',required=True)
parser.add_argument('--browser',default='msedge')
args=parser.parse_args()
if not re.fullmatch(r'atikha_test_phase3_[a-z0-9]+',args.database):
    parser.error('Use an explicitly named disposable Phase 3 database')
script=Path(__file__).with_name('test_accounting_browser.py')
raise SystemExit(subprocess.call([sys.executable,str(script),'--database='+args.database,'--browser='+args.browser]))
