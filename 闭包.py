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

t = make_avg()
print(t(2),t(8))