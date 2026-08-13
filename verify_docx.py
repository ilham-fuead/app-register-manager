import zipfile

z = zipfile.ZipFile('App_Manager_Deployment_Guide_and_User_Manual.docx')
print('Parts in DOCX:')
for info in z.infolist():
    print('  {} ({} bytes)'.format(info.filename, info.file_size))

doc_xml = z.read('word/document.xml').decode('utf-8', errors='replace')
print()
print('TOC field found:', 'TOC' in doc_xml and 'fldChar' in doc_xml)
print('PAGE field found:', 'PAGE' in doc_xml)
print('NUMPAGES field found:', 'NUMPAGES' in doc_xml)
print('Total XML elements (approx):', doc_xml.count('<'))

# Check footer
footer_path = None
for info in z.infolist():
    if 'footer' in info.filename and info.filename.endswith('.xml'):
        footer_path = info.filename
        break
if footer_path:
    footer_xml = z.read(footer_path).decode('utf-8', errors='replace')
    print('Footer PAGE field:', 'PAGE' in footer_xml)
    print('Footer NUMPAGES field:', 'NUMPAGES' in footer_xml)

# Count heading styles used
print('Heading1 count:', doc_xml.count('Heading1'))
print('Heading2 count:', doc_xml.count('Heading2'))
print('Heading3 count:', doc_xml.count('Heading3'))
z.close()
