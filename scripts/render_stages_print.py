"""Read-only visual verification of existing isolated browser print PDFs."""
from pathlib import Path
import argparse,hashlib,json
import pypdfium2
from pypdf import PdfReader
args=argparse.ArgumentParser();args.add_argument('pdf');args.add_argument('--bundle',action='store_true');a=args.parse_args();root=Path(__file__).resolve().parent.parent;private=(root/'.migration-private').resolve();pdf=Path(a.pdf).resolve();assert pdf.is_relative_to(private) and pdf.suffix=='.pdf' and pdf.is_file()
reader=PdfReader(pdf);doc=pypdfium2.PdfDocument(str(pdf));pages=[]
for n,page in enumerate(doc):
 target=pdf.with_name(pdf.stem+'-page-'+str(n+1)+'.png');page.render(scale=1.5).to_pil().save(target);text=reader.pages[n].extract_text();pages.append({'page':n+1,'width_pt':float(reader.pages[n].mediabox.width),'height_pt':float(reader.pages[n].mediabox.height),'text_characters':len(text),'render':str(target)})
 if a.bundle:assert 'Atikha Finance' in text and 'Frozen revision' in text and ('Page '+str(n+1)+' of '+str(len(reader.pages))) in text,'Every frozen page needs exact identity and page numbering'
result={'pdf_sha256':hashlib.sha256(pdf.read_bytes()).hexdigest(),'pages':pages,'read_only_render':True};pdf.with_suffix('.render.json').write_text(json.dumps(result,indent=2),encoding='utf-8');print(json.dumps(result))
