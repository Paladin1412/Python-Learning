from dataclasses import dataclass

@dataclass
class man:
    name: str
    age:int
man1 = man("s",12)
man2 = man("s",12)
print(man1 is man2)