"""Optional isolated regression pointer; default historical drivers retain their existing contract."""
from pathlib import Path
import os
def test_pointer(default):
 value=os.getenv('ATIKHA_TEST_POINTER')
 if not value:return default
 root=Path(__file__).resolve().parent.parent/'.migration-private';path=Path(value).resolve()
 assert path.is_relative_to(root.resolve()) and path.name=='redesign-regression-current.json','Private redesign regression pointer required'
 return path
