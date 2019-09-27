from collections import namedtuple

a = namedtuple('Cardbook','name age')
perry = a(name="ss",age="sfs")
book = a(name="ss",age='sfs')

print(book)