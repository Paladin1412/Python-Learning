from collections import namedtuple

a = namedtuple('Cardbook','name age')
perry = a(name="ss",age="sfs")
book = a(name="ss",age='sfs')

print(book)
class rw(object):
    def __init__(self,name,sex):
        self.name = name
        self.sex = sex
    def __eq__(self, other):
        if self.name == other.name and self.sex == other.sex:
            return True
        return False
    def __hash__(self):
        return hash(self.name + self.sex)

alex = rw("ak","man")
axle = rw("al","man")
alxe = rw("ak","man")
listc = []
listc.append(alex)
listc.append(axle)
listc.append(alxe)
for i in range(1,len(listc)):
    a = set(listc[i],listc[i-1])

print(listc)
