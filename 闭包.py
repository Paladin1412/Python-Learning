def make_avg():
    count = 0
    total = 0
    def avger(new_value):
        nonlocal count,total
        print(count, total)
        count +=1
        total+=new_value
        return total/new_value
    return avger

b =6
def f1(a):
    print(a)
    print(b)
    # b=9
f1(3)
